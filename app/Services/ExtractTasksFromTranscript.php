<?php

namespace App\Services;

use App\Models\AudioClip;
use App\Models\TaskItem;
use App\Models\TaskList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExtractTasksFromTranscript
{
    public function __construct(
        private LlmClient $llm,
    ) {
    }

    public function handle(AudioClip $clip): void
    {
        $text = trim((string) ($clip->transcript_text ?? ''));
        if ($text === '') {
            $clip->tasks_extract_status = AudioClip::TASKS_EXTRACT_ERROR;
            $clip->tasks_extract_error = 'Нет текста транскрипта для извлечения задач.';
            $clip->save();

            return;
        }

        try {
            $parsed = $this->askLlm($text);
            $tasks = $parsed['tasks'];
            $title = $parsed['title'];

            if ($tasks === []) {
                $clip->tasks_extract_status = AudioClip::TASKS_EXTRACT_EMPTY;
                $clip->tasks_extract_error = 'Явных задач в тексте не найдено.';
                $clip->save();

                return;
            }

            DB::transaction(function () use ($clip, $tasks, $title) {
                $list = TaskList::query()->create([
                    'user_id' => $clip->user_id,
                    'audio_clip_id' => $clip->id,
                    'title' => $title !== '' ? $title : 'Задачи',
                    'source' => TaskList::SOURCE_AUDIO_CLIP_TRANSCRIPT,
                ]);

                foreach ($tasks as $index => $body) {
                    TaskItem::query()->create([
                        'task_list_id' => $list->id,
                        'body' => $body,
                        'position' => $index,
                        'status' => TaskItem::STATUS_OPEN,
                    ]);
                }

                $clip->tasks_extract_status = AudioClip::TASKS_EXTRACT_DONE;
                $clip->tasks_extract_error = null;
                $clip->save();
            });
        } catch (Throwable $exception) {
            Log::warning('ExtractTasksFromTranscript failed: '.$exception->getMessage(), [
                'audio_clip_id' => $clip->id,
            ]);
            $clip->tasks_extract_status = AudioClip::TASKS_EXTRACT_ERROR;
            $clip->tasks_extract_error = mb_substr($exception->getMessage(), 0, 1000);
            $clip->save();
        }
    }

    /**
     * @return array{title: string, tasks: list<string>}
     */
    private function askLlm(string $transcript): array
    {
        $system = 'Ты парсер задач. Отвечай только JSON без markdown. '
            .'Формат: {"title":"строка","tasks":[{"text":"строка"}]}. '
            .'Бери формулировки с глаголами действия (купить, позвонить, сделать, записаться…). '
            .'Если дел нет: {"title":"","tasks":[]}.';

        $user = "Пример ответа для «Нужно купить хлеб и позвонить маме»:\n"
            ."{\"title\":\"Дела\",\"tasks\":[{\"text\":\"купить хлеб\"},{\"text\":\"позвонить маме\"}]}\n\n"
            ."Транскрипт:\n".$transcript;

        $raw = $this->llm->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], ['max_tokens' => 320]);

        Log::info('ExtractTasksFromTranscript LLM raw', [
            'preview' => mb_substr($raw, 0, 500),
        ]);

        return $this->parseTasksJson($raw);
    }

    /**
     * @return array{title: string, tasks: list<string>}
     */
    private function parseTasksJson(string $raw): array
    {
        $json = $this->extractJsonObject($raw);
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException('LLM вернул не-JSON ответ.');
        }

        $title = isset($data['title']) && is_string($data['title'])
            ? trim($data['title'])
            : '';

        $tasksRaw = $data['tasks'] ?? null;
        if (! is_array($tasksRaw)) {
            throw new RuntimeException('LLM JSON: нет массива tasks.');
        }

        $tasks = [];
        foreach ($tasksRaw as $item) {
            if (is_string($item)) {
                $text = trim($item);
            } elseif (is_array($item) && isset($item['text']) && is_string($item['text'])) {
                $text = trim($item['text']);
            } else {
                continue;
            }
            if ($text !== '') {
                $tasks[] = mb_substr($text, 0, 2000);
            }
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'tasks' => $tasks,
        ];
    }

    private function extractJsonObject(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $trimmed, $m)) {
            return $m[1];
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('LLM ответ не содержит JSON-объект.');
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
