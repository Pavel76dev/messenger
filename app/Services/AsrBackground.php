<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Фоновый artisan (ASR / extract-tasks): HTTP сразу 202,
 * OpenServer/браузер не «залипают» белым экраном.
 */
class AsrBackground
{
    public function startTranscribe(int $audioClipId): void
    {
        $this->startCommand('audio-clip:transcribe', $audioClipId, 'transcribe');
    }

    public function startExtractTasks(int $audioClipId): void
    {
        $this->startCommand('audio-clip:extract-tasks', $audioClipId, 'extract-tasks');
    }

    private function startCommand(string $artisanCommand, int $audioClipId, string $jobLabel): void
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
            $this->startWindows($php, $artisan, $base, $artisanCommand, $audioClipId, $jobLabel, $logFile);

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan)
            .' '.$artisanCommand.' '.escapeshellarg((string) $audioClipId)
            .' >> '.escapeshellarg($logFile).' 2>&1 &';
        exec($cmd);
        Log::info('AsrBackground: unix spawn', [
            'command' => $artisanCommand,
            'audio_clip_id' => $audioClipId,
            'php' => $php,
        ]);
    }

    private function startWindows(
        string $php,
        string $artisan,
        string $base,
        string $artisanCommand,
        int $audioClipId,
        string $jobLabel,
        string $logFile
    ): void {
        $id = (string) $audioClipId;
        $jobsDir = storage_path('app/asr-jobs');
        if (! is_dir($jobsDir) && ! mkdir($jobsDir, 0775, true) && ! is_dir($jobsDir)) {
            throw new RuntimeException('Не удалось создать каталог asr-jobs.');
        }

        $script = $jobsDir.DIRECTORY_SEPARATOR.$jobLabel.'-'.$id.'-'.bin2hex(random_bytes(4)).'.cmd';
        $content = implode("\r\n", [
            '@echo off',
            'cd /d '.escapeshellarg($base),
            'echo ['.date('c').'] start '.$jobLabel.' clip '.$id.' php='.$php.' >> '.escapeshellarg($logFile),
            escapeshellarg($php).' '.escapeshellarg($artisan).' '.$artisanCommand.' '.$id
                .' >> '.escapeshellarg($logFile).' 2>&1',
            'echo ['.date('c').'] end '.$jobLabel.' clip '.$id.' exit=%ERRORLEVEL% >> '.escapeshellarg($logFile),
            '',
        ]);
        if (file_put_contents($script, $content) === false) {
            throw new RuntimeException('Не удалось записать cmd-обёртку фона.');
        }

        $this->purgeOldJobScripts($jobsDir);
        $spawned = $this->spawnDetachedWindows($script, $this->windowsCmd());
        Log::info('AsrBackground: windows spawn', [
            'command' => $artisanCommand,
            'audio_clip_id' => $audioClipId,
            'php' => $php,
            'script' => $script,
            'method' => $spawned,
        ]);
    }

    /**
     * @return string Имя метода запуска (для логов)
     */
    private function spawnDetachedWindows(string $scriptPath, string $cmdExe): string
    {
        $powershell = $this->windowsPowerShell();

        $startCmd = $cmdExe.' /c start /B "" '.escapeshellarg($cmdExe).' /c call '.escapeshellarg($scriptPath);
        $handle = @popen($startCmd, 'r');
        if (is_resource($handle)) {
            pclose($handle);

            return 'cmd-start';
        }

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
            'Не удалось запустить фоновую задачу (popen недоступен).'
        );
    }

    private function purgeOldJobScripts(string $jobsDir): void
    {
        $cutoff = time() - 3600;
        $patterns = [
            $jobsDir.DIRECTORY_SEPARATOR.'transcribe-*.cmd',
            $jobsDir.DIRECTORY_SEPARATOR.'extract-tasks-*.cmd',
            $jobsDir.DIRECTORY_SEPARATOR.'spawn-*.ps1',
        ];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file) && filemtime($file) < $cutoff) {
                    @unlink($file);
                }
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

    private function resolvePhpCli(): string
    {
        foreach ($this->phpCliCandidates() as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Не найден php.exe для фона. Задайте ASR_PHP в .env. '
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
                $candidates[] = dirname($binary).DIRECTORY_SEPARATOR.'php.exe';
            }

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
