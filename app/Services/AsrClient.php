<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Локальная транскрибация внутри приложения: subprocess → services/asr/cli.py.
 * Не ходит в сеть и не требует отдельного HTTP-демона (в отличие от api-llm).
 */
class AsrClient
{
    public function transcribe(string $absolutePath, string $filename = '', string $mime = 'audio/webm'): string
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Файл клипа не найден на диске.');
        }

        $workdir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) config('asr.workdir')), DIRECTORY_SEPARATOR);
        $cli = (string) config('asr.cli', 'cli.py');
        $cliPath = $workdir.DIRECTORY_SEPARATOR.$cli;
        if (! is_file($cliPath)) {
            throw new RuntimeException('Не найден ASR CLI: '.$cliPath);
        }

        $python = $this->resolvePython($workdir);
        $timeout = max(30, (int) config('asr.timeout_sec', 300));

        $result = Process::path($workdir)
            ->timeout($timeout)
            ->env([
                // На Windows без этого stdout часто cp1251 → json_decode ломается.
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
            ])
            ->run([
                $python,
                $cli,
                $absolutePath,
            ]);

        if (! $result->successful()) {
            $err = trim($result->errorOutput() ?: $result->output());
            throw new RuntimeException(
                'ASR локально: '.mb_substr($err !== '' ? $err : 'код '.$result->exitCode(), 0, 500)
            );
        }

        $raw = trim($result->output());
        $json = json_decode($raw, true);
        if (! is_array($json) || ! array_key_exists('text', $json)) {
            // Fallback: консоль Windows могла отдать cp1251, если CLI старый.
            if (function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
                if (is_string($converted)) {
                    $json = json_decode($converted, true);
                }
            }
        }
        if (! is_array($json) || ! array_key_exists('text', $json)) {
            throw new RuntimeException('ASR CLI вернул неожиданный ответ.');
        }

        $text = $json['text'];
        if (! is_string($text)) {
            throw new RuntimeException('ASR CLI: поле text не строка.');
        }

        return trim($text);
    }

    public function isAvailable(): bool
    {
        try {
            $workdir = rtrim((string) config('asr.workdir'), DIRECTORY_SEPARATOR);
            $this->resolvePython($workdir);

            return is_file($workdir.DIRECTORY_SEPARATOR.(string) config('asr.cli', 'cli.py'));
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolvePython(string $workdir): string
    {
        $configured = config('asr.python');
        if (is_string($configured) && $configured !== '') {
            if (! is_file($configured) && ! $this->isRunnable($configured)) {
                throw new RuntimeException('ASR_PYTHON не найден: '.$configured);
            }

            return $configured;
        }

        $candidates = [
            $workdir.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'Scripts'.DIRECTORY_SEPARATOR.'python.exe',
            $workdir.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'python',
            $workdir.DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'python3',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException(
            'Нет Python для ASR. Создайте venv: cd services/asr && python -m venv .venv '
            .'&& .venv\\Scripts\\python.exe -m pip install -r requirements.txt'
        );
    }

    private function isRunnable(string $binary): bool
    {
        if (is_file($binary)) {
            return true;
        }

        // PATH binary name (linux/mac)
        return ! str_contains($binary, DIRECTORY_SEPARATOR) && ! str_contains($binary, '/');
    }
}
