<?php

namespace App\Services;

use RuntimeException;

/**
 * Запуск транскрибации в отдельном процессе PHP, чтобы HTTP-запрос
 * сразу вернул 202 и OpenServer/браузер не «залипали» белым экраном.
 */
class AsrBackground
{
    public function startTranscribe(int $audioClipId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        if (! is_file($artisan)) {
            throw new RuntimeException('Не найден artisan.');
        }

        $id = (string) $audioClipId;

        if (PHP_OS_FAMILY === 'Windows') {
            // start /B — без нового окна, сразу отсоединяемся
            $cmd = 'start /B "" '.escapeshellarg($php).' '.escapeshellarg($artisan)
                .' audio-clip:transcribe '.$id;
            pclose(popen($cmd, 'r'));

            return;
        }

        $cmd = escapeshellarg($php).' '.escapeshellarg($artisan)
            .' audio-clip:transcribe '.escapeshellarg($id)
            .' > /dev/null 2>&1 &';
        exec($cmd);
    }
}
