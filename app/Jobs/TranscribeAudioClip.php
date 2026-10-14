<?php

namespace App\Jobs;

use App\Models\AudioClip;
use App\Services\AsrClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeAudioClip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 360;

    public function __construct(public int $audioClipId)
    {
    }

    public function handle(AsrClient $asr): void
    {
        $clip = AudioClip::query()->find($this->audioClipId);
        if (! $clip) {
            return;
        }

        $absolute = Storage::disk('public')->path($clip->path);
        $basename = basename($clip->path);

        try {
            $text = $asr->transcribe($absolute, $basename, (string) $clip->mime);
            $clip->transcript_status = 'done';
            $clip->transcript_text = $text;
            $clip->transcript_error = null;
            $clip->save();
        } catch (Throwable $exception) {
            Log::warning('TranscribeAudioClip failed: '.$exception->getMessage(), [
                'audio_clip_id' => $this->audioClipId,
            ]);
            $clip->transcript_status = 'error';
            $clip->transcript_error = mb_substr($exception->getMessage(), 0, 1000);
            $clip->save();
        }
    }
}
