<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Запуск транскрибации в отдельном процессе PHP, чтобы HTTP-запрос
 * сразу вернул 202 и OpenServer/браузер не «залипали» белым экраном.
 */
class AsrBackground
{
    public function startTranscribe(int $audioClipId): void
    {
        $php = $this->resolvePhpCli();
        $artisan = base_path('artisan');
        if (! is_file($artisan)) {
            throw new RuntimeException('Не найден artisan.');
        }

        $base = base_path();
        $logFile = storage_path('logs/asr-background.log');
        $this->ensureLogFile($logFile);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->startWindows($php, $artisan, $base, $audioClipId, $logFile);

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan)
            .' audio-clip:transcribe '.escapeshellarg((string) $audioClipId)
            .' >> '.escapeshellarg($logFile).' 2>&1 &';
        exec($cmd);
        Log::info('AsrBackground: unix spawn', ['audio_clip_id' => $audioClipId, 'php' => $php]);
    }

    private function startWindows(
        string $php,
        string $artisan,
        string $base,
        int $audioClipId,
        string $logFile
    ): void {
        $id = (string) $audioClipId;
        $jobsDir = storage_path('app/asr-jobs');
        if (! is_dir($jobsDir) && ! mkdir($jobsDir, 0775, true) && ! is_dir($jobsDir)) {
            throw new RuntimeException('Не удалось создать каталог asr-jobs.');
        }

        // .cmd с явным cd — Apache/CGI часто стартует не из корня проекта.
        $script = $jobsDir.DIRECTORY_SEPARATOR.'transcribe-'.$id.'-'.bin2hex(random_bytes(4)).'.cmd';
        $content = implode("\r\n", [
            '@echo off',
            'cd /d '.escapeshellarg($base),
            'echo ['.date('c').'] start clip '.$id.' php='.$php.' >> '.escapeshellarg($logFile),
            escapeshellarg($php).' '.escapeshellarg($artisan).' audio-clip:transcribe '.$id
                .' >> '.escapeshellarg($logFile).' 2>&1',
            'echo ['.date('c').'] end clip '.$id.' exit=%ERRORLEVEL% >> '.escapeshellarg($logFile),
            '',
        ]);
        if (file_put_contents($script, $content) === false) {
            throw new RuntimeException('Не удалось записать cmd-обёртку ASR.');
        }

        $this->purgeOldJobScripts($jobsDir);
        $spawned = $this->spawnDetachedWindows($script, $this->windowsCmd());
        Log::info('AsrBackground: windows spawn', [
            'audio_clip_id' => $audioClipId,
            'php' => $php,
            'script' => $script,
            'method' => $spawned,
        ]);
        // Не пишем в тот же asr-background.log здесь: .cmd уже открыл его → EAGAIN на Windows.
    }

    /**
     * @return string Имя метода запуска (для логов)
     */
    private function spawnDetachedWindows(string $scriptPath, string $cmdExe): string
    {
        $powershell = $this->windowsPowerShell();

        // start /B "" cmd /c call "script.cmd" — иначе .cmd иногда «не находится».
        $startCmd = $cmdExe.' /c start /B "" '.escapeshellarg($cmdExe).' /c call '.escapeshellarg($scriptPath);
        $handle = @popen($startCmd, 'r');
        if (is_resource($handle)) {
            pclose($handle);

            return 'cmd-start';
        }

        // Fallback: PowerShell Start-Process через -File (без вложенных -Command-кавычек).
        if ($powershell !== null) {
            $ps1 = dirname($scriptPath).DIRECTORY_SEPARATOR.'spawn-'.bin2hex(random_bytes(4)).'.ps1';
            $psBody = 'Start-Process -FilePath '.escapeshellarg($scriptPath)." -WindowStyle Hidden\r\n";
            if (@file_put_contents($ps1, $psBody) !== false) {
                $psCmd = escapeshellarg($powershell)
                    .' -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg($ps1);
                $handle = @popen($psCmd, 'r');
                if (is_resource($handle)) {
                    pclose($handle);

                    return 'powershell-file';
                }
            }
        }

        throw new RuntimeException(
            'Не удалось запустить фоновую транскрибацию (popen недоступен).'
        );
    }

    private function purgeOldJobScripts(string $jobsDir): void
    {
        $cutoff = time() - 3600;
        foreach (glob($jobsDir.DIRECTORY_SEPARATOR.'transcribe-*.cmd') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
        foreach (glob($jobsDir.DIRECTORY_SEPARATOR.'spawn-*.ps1') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function windowsPowerShell(): ?string
    {
        $root = getenv('SystemRoot') ?: 'C:\\Windows';
        $candidates = [
            $root.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe',
            $root.'\\SysWOW64\\WindowsPowerShell\\v1.0\\powershell.exe',
            'powershell.exe',
        ];
        foreach ($candidates as $path) {
            if ($path === 'powershell.exe' || is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function windowsCmd(): string
    {
        $comspec = getenv('ComSpec');
        if (is_string($comspec) && $comspec !== '' && is_file($comspec)) {
            return $comspec;
        }
        $root = getenv('SystemRoot') ?: 'C:\\Windows';
        $cmd = $root.'\\System32\\cmd.exe';

        return is_file($cmd) ? $cmd : 'cmd.exe';
    }

    /**
     * Нужен настоящий php.exe для artisan.
     * OpenServer + apache2handler: PHP_BINARY = httpd.exe (рядом php.exe нет).
     */
    private function resolvePhpCli(): string
    {
        foreach ($this->phpCliCandidates() as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Не найден php.exe для ASR. Задайте ASR_PHP в .env. '
            .'PHP_BINARY='.PHP_BINARY.' PHP_BINDIR='.PHP_BINDIR
        );
    }

    /**
     * @return list<string>
     */
    private function phpCliCandidates(): array
    {
        $configured = config('asr.php_cli');
        $candidates = [];

        if (is_string($configured) && $configured !== '') {
            $candidates[] = $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $bindir = rtrim((string) PHP_BINDIR, "\\/");
            if ($bindir !== '') {
                $candidates[] = $bindir.DIRECTORY_SEPARATOR.'php.exe';
            }

            $extDir = (string) ini_get('extension_dir');
            if ($extDir !== '' && ! str_starts_with($extDir, '.')) {
                $candidates[] = dirname($extDir).DIRECTORY_SEPARATOR.'php.exe';
            }

            $binary = PHP_BINARY;
            $base = strtolower(basename($binary));
            if ($base === 'php.exe' && is_file($binary)) {
                $candidates[] = $binary;
            } elseif ($binary !== '') {
                $neighbor = dirname($binary).DIRECTORY_SEPARATOR.'php.exe';
                $candidates[] = $neighbor;
            }

            // OpenServer: modules/php/PHP_8.1/php.exe (версия = текущий PHP модуля Apache)
            $verFolder = 'PHP_'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
            foreach (['C:\\OSPanel\\modules\\php', 'C:\\ospanel\\modules\\php'] as $root) {
                $candidates[] = $root.DIRECTORY_SEPARATOR.$verFolder.DIRECTORY_SEPARATOR.'php.exe';
            }

            return array_values(array_unique($candidates));
        }

        $binary = PHP_BINARY;
        if ($binary !== '' && is_file($binary) && ! str_contains(basename($binary), 'php-fpm')) {
            $candidates[] = $binary;
        }

        foreach (['php', 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, 'php'.PHP_MAJOR_VERSION] as $name) {
            $which = trim((string) shell_exec('command -v '.escapeshellarg($name).' 2>/dev/null'));
            if ($which !== '') {
                $candidates[] = $which;
            }
        }

        return array_values(array_unique($candidates));
    }

    private function ensureLogFile(string $logFile): void
    {
        $dir = dirname($logFile);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог логов.');
        }
        if (! is_file($logFile)) {
            @file_put_contents($logFile, '');
        }
    }
}
