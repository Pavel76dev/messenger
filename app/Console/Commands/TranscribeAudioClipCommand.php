<?php

namespace App\Console\Commands;

use App\Models\AudioClip;
use App\Services\AsrClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeAudioClipCommand extends Command
{
    protected $signature = 'audio-clip:transcribe {id : ID audio_clips}';

    protected $description = 'Локальная транскрибация клипа (фон, без HTTP-демона)';

    public function handle(AsrClient $asr): int
    {
        $id = (int) $this->argument('id');
        $clip = AudioClip::query()->find($id);
        if (! $clip) {
            $this->error("Клип {$id} не найден");

            return self::FAILURE;
        }

        $absolute = Storage::disk('public')->path($clip->path);

        try {
            if (! is_file($absolute)) {
                throw new \RuntimeException('Файл клипа не найден на диске.');
            }

            $text = $asr->transcribe($absolute, basename($clip->path), (string) $clip->mime);
            $clip->transcript_status = AudioClip::TRANSCRIPT_DONE;
            $clip->transcript_text = $text;
            $clip->transcript_error = null;
            $clip->save();
            $this->info('OK clip '.$id);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::warning('audio-clip:transcribe failed: '.$exception->getMessage(), [
                'audio_clip_id' => $id,
            ]);
            $clip->transcript_status = AudioClip::TRANSCRIPT_ERROR;
            $clip->transcript_error = mb_substr($exception->getMessage(), 0, 1000);
            $clip->save();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
