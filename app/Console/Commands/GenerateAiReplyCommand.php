<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAiReply;
use Illuminate\Console\Command;
use Throwable;

class GenerateAiReplyCommand extends Command
{
    protected $signature = 'ai:reply
        {conversation_id : ID диалога с ботом}
        {message_id : ID сообщения пользователя-триггера}';

    protected $description = 'Фоновая генерация ответа ассистента (роутер + workspace + LLM)';

    public function handle(): int
    {
        $conversationId = (int) $this->argument('conversation_id');
        $messageId = (int) $this->argument('message_id');

        if ($conversationId < 1 || $messageId < 1) {
            $this->error('Некорректные id');

            return self::FAILURE;
        }

        @set_time_limit(0);

        try {
            $job = new GenerateAiReply($conversationId, $messageId);
            $job->handle(
                app(\App\Services\AiBot::class),
                app(\App\Services\LlmClient::class),
                app(\App\Services\WorkspaceNeedRouter::class),
                app(\App\Services\AssistantWorkspaceContext::class),
            );
            $this->info("OK ai:reply conversation={$conversationId} message={$messageId}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
