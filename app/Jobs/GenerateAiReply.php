<?php

namespace App\Jobs;

use App\Events\MessageCreated;
use App\Http\Controllers\Api\MessageController;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiBot;
use App\Services\LlmClient;
use App\Support\SafeBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $conversationId,
        public int $triggerMessageId,
    ) {
    }

    public function handle(AiBot $aiBot, LlmClient $llm): void
    {
        $bot = $aiBot->find();
        if (! $bot) {
            Log::warning('GenerateAiReply: bot user missing');

            return;
        }

        $conversation = Conversation::query()->find($this->conversationId);
        if (! $conversation || ! $conversation->isDirect()) {
            return;
        }

        if (! $conversation->hasParticipant((int) $bot->id)) {
            return;
        }

        $trigger = Message::query()->find($this->triggerMessageId);
        if (! $trigger || (int) $trigger->conversation_id !== (int) $conversation->id) {
            return;
        }

        if ((int) $trigger->user_id === (int) $bot->id) {
            return;
        }

        $body = trim((string) ($trigger->body ?? ''));
        if ($body === '') {
            return;
        }

        $limit = max(2, (int) config('llm.history_limit', 20));
        $history = $conversation->messages()
            ->where('id', '<=', $trigger->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $messages = [
            [
                'role' => 'system',
                'content' => (string) config('llm.system_prompt'),
            ],
        ];

        foreach ($history as $item) {
            $text = trim((string) ($item->body ?? ''));
            if ($text === '') {
                continue;
            }
            $messages[] = [
                'role' => (int) $item->user_id === (int) $bot->id ? 'assistant' : 'user',
                'content' => $text,
            ];
        }

        try {
            $reply = $llm->chat($messages);
        } catch (Throwable $exception) {
            Log::warning('GenerateAiReply LLM failed: '.$exception->getMessage(), [
                'conversation_id' => $this->conversationId,
                'message_id' => $this->triggerMessageId,
            ]);
            $reply = 'Сейчас не могу ответить: сервис ИИ недоступен. Попробуйте позже.';
        }

        $message = $conversation->messages()->create([
            'user_id' => $bot->id,
            'body' => mb_substr($reply, 0, 5000),
        ]);
        $conversation->touch();

        $message->load(['user:id,name,avatar_path', 'attachments', 'reactions']);

        /** @var MessageController $serializer */
        $serializer = app(MessageController::class);
        $payload = $serializer->serializeForApi($message, null);

        // Всем участникам, включая автора триггера (не toOthers).
        SafeBroadcast::now(new MessageCreated((int) $conversation->id, $payload));
    }
}
