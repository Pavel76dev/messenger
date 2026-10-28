<?php

namespace App\Jobs;

use App\Events\MessageCreated;
use App\Http\Controllers\Api\MessageController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\AiBot;
use App\Services\AssistantWorkspaceContext;
use App\Services\LlmClient;
use App\Services\WorkspaceNeedRouter;
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

    public function handle(
        AiBot $aiBot,
        LlmClient $llm,
        WorkspaceNeedRouter $router,
        AssistantWorkspaceContext $workspace,
    ): void {
        // Router + reply + workspace: два (и более) долгих HTTP к api-llm.
        @set_time_limit(0);

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

        $author = User::query()->find($trigger->user_id);
        if (! $author) {
            return;
        }

        $historyMessages = $this->buildHistoryMessages($conversation, $bot, $trigger);

        $needWorkspace = true;
        try {
            $needWorkspace = $router->needsWorkspace($author, $body);
        } catch (Throwable $exception) {
            Log::warning('GenerateAiReply router failed: '.$exception->getMessage());
            $needWorkspace = true;
        }

        Log::info('GenerateAiReply router', [
            'conversation_id' => $this->conversationId,
            'message_id' => $this->triggerMessageId,
            'need_workspace' => $needWorkspace,
        ]);

        $messages = [
            [
                'role' => 'system',
                'content' => (string) config('llm.system_prompt'),
            ],
        ];

        if ($needWorkspace) {
            try {
                $context = $workspace->build($author, $body);
                $contextChars = mb_strlen($context);
                Log::info('GenerateAiReply workspace built', [
                    'conversation_id' => $this->conversationId,
                    'message_id' => $this->triggerMessageId,
                    'context_chars' => $contextChars,
                ]);
                if (trim($context) !== '') {
                    $messages[] = [
                        'role' => 'system',
                        'content' => "Ниже — доступные данные пользователя. Используй их в ответе.\n"
                            ."Не говори, что у тебя нет доступа к задачам, чатам или файлам.\n\n"
                            .$context,
                    ];
                }
            } catch (Throwable $exception) {
                Log::warning('GenerateAiReply workspace build failed: '.$exception->getMessage());
                $messages[] = [
                    'role' => 'system',
                    'content' => 'Контекст данных пользователя сейчас недоступен (ошибка сборки). '
                        .'Ответь по истории чата; не выдумывай чужие переписки и файлы.',
                ];
            }
        }

        foreach ($historyMessages as $item) {
            $messages[] = $item;
        }

        try {
            // Для запросов с контекстом просим короткий ответ — меньше шансов зависнуть на локальной модели.
            $replyMaxTokens = $needWorkspace ? 700 : 1024;
            $reply = $llm->chat($messages, ['max_tokens' => $replyMaxTokens]);
        } catch (Throwable $exception) {
            Log::warning('GenerateAiReply LLM failed: '.$exception->getMessage(), [
                'conversation_id' => $this->conversationId,
                'message_id' => $this->triggerMessageId,
            ]);
            $hint = mb_substr(trim($exception->getMessage()), 0, 180);
            $reply = 'Сейчас не могу ответить: сервис ИИ недоступен.'
                .($hint !== '' ? ' ('.$hint.')' : '')
                .' Попробуйте позже.';
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

        SafeBroadcast::now(new MessageCreated((int) $conversation->id, $payload));
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function buildHistoryMessages(Conversation $conversation, User $bot, Message $trigger): array
    {
        $limit = max(2, (int) config('llm.history_limit', 20));
        $history = $conversation->messages()
            ->where('id', '<=', $trigger->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values();

        $messages = [];
        foreach ($history as $item) {
            $text = trim((string) ($item->body ?? ''));
            if ($text === '') {
                continue;
            }
            $isBot = (int) $item->user_id === (int) $bot->id;
            if ($isBot && $this->isLlmInfrastructureNoise($text)) {
                continue;
            }
            $messages[] = [
                'role' => $isBot ? 'assistant' : 'user',
                'content' => $text,
            ];
        }

        return $messages;
    }

    private function isLlmInfrastructureNoise(string $text): bool
    {
        $lower = mb_strtolower($text);

        if (str_contains($lower, 'сервис ии недоступен')) {
            return true;
        }
        if (str_contains($lower, 'сервис ии временно недоступен')) {
            return true;
        }
        if (str_contains($lower, '127.0.0.1:18050') && str_contains($lower, 'curl')) {
            return true;
        }
        if (str_contains($lower, 'curl error 28') || str_contains($lower, 'operation timed out')) {
            return true;
        }

        return false;
    }
}
