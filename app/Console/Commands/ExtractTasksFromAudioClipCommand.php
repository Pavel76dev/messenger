<?php

namespace App\Console\Commands;

use App\Models\AudioClip;
use App\Services\ExtractTasksFromTranscript;
use Illuminate\Console\Command;

class ExtractTasksFromAudioClipCommand extends Command
{
    protected $signature = 'audio-clip:extract-tasks {id : ID audio_clips}';

    protected $description = 'Извлечь список задач из транскрипта клипа через LLM (фон)';

    public function handle(ExtractTasksFromTranscript $extractor): int
    {
        $id = (int) $this->argument('id');
        $clip = AudioClip::query()->find($id);
        if (! $clip) {
            $this->error("Клип {$id} не найден");

            return self::FAILURE;
        }

        $extractor->handle($clip);
        $clip->refresh();
        $this->info('extract '.$id.' status='.$clip->tasks_extract_status);

        return $clip->tasks_extract_status === AudioClip::TASKS_EXTRACT_ERROR
            ? self::FAILURE
            : self::SUCCESS;
    }
}
