<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceNeedRouter
{
    public function __construct(
        private LlmClient $llm,
        private AssistantWorkspaceContext $workspace,
    ) {
    }

    /**
     * Решение только через LLM (первое обращение).
     * При ошибке вызова / парсинга — true (fail-open).
     */
    public function needsWorkspace(User $user, string $userMessage): bool
    {
        $catalog = $this->workspace->buildCatalog($user);
        $system = (string) config('llm.router_prompt');
        $maxTokens = max(32, (int) config('llm.router_max_tokens', 64));

        $routerTimeout = max(15, (int) config('llm.router_timeout_sec', 45));

        try {
            $raw = $this->llm->chat([
                ['role' => 'system', 'content' => $system],
                [
                    'role' => 'user',
                    'content' => "Запрос пользователя:\n".$userMessage."\n\n".$catalog,
                ],
            ], [
                'max_tokens' => $maxTokens,
                'timeout' => $routerTimeout,
            ]);
        } catch (Throwable $exception) {
            Log::warning('WorkspaceNeedRouter LLM failed, assume need_workspace=true: '.$exception->getMessage());

            return true;
        }

        $parsed = $this->parseNeedWorkspace($raw);
        if ($parsed === null) {
            Log::info('WorkspaceNeedRouter parse fail, assume true', [
                'preview' => mb_substr($raw, 0, 200),
            ]);

            return true;
        }

        Log::info('WorkspaceNeedRouter LLM decision', [
            'need_workspace' => $parsed,
            'preview' => mb_substr($raw, 0, 120),
        ]);

        return $parsed;
    }

    private function parseNeedWorkspace(string $raw): ?bool
    {
        $json = $this->extractJsonObject($raw);
        if ($json === null) {
            $lower = mb_strtolower($raw);
            if (str_contains($lower, 'need_workspace') && str_contains($lower, 'false')) {
                return false;
            }
            if (str_contains($lower, 'need_workspace') && str_contains($lower, 'true')) {
                return true;
            }

            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data) || ! array_key_exists('need_workspace', $data)) {
            return null;
        }

        $value = $data['need_workspace'];
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['true', '1', 'yes', 'да'], true)) {
                return true;
            }
            if (in_array($v, ['false', '0', 'no', 'нет'], true)) {
                return false;
            }
        }

        return null;
    }

    private function extractJsonObject(string $raw): ?string
    {
        $trimmed = trim($raw);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $trimmed, $m)) {
            return $m[1];
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
