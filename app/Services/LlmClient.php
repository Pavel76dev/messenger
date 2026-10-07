<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlmClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        $base = rtrim((string) config('llm.api_url'), '/');
        if ($base === '') {
            throw new RuntimeException('LLM_API_URL не задан.');
        }

        $payload = [
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => 1024,
        ];

        $target = (string) config('llm.target', 'auto');
        if ($target !== '' && $target !== 'auto') {
            $payload['target'] = $target;
        }

        $model = config('llm.model');
        if (is_string($model) && $model !== '') {
            $payload['model'] = $model;
        }

        $request = Http::timeout((int) config('llm.timeout_sec', 120))
            ->acceptJson()
            ->asJson();

        $token = config('llm.api_token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->post("{$base}/api/chat", $payload);

        if (! $response->successful()) {
            $detail = $response->json('detail');
            $message = is_array($detail)
                ? (string) ($detail['message'] ?? json_encode($detail, JSON_UNESCAPED_UNICODE))
                : (string) ($detail ?: $response->body());

            throw new RuntimeException(
                'api-llm HTTP '.$response->status().': '.mb_substr(trim($message), 0, 500)
            );
        }

        $content = $response->json('content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('api-llm вернул пустой ответ.');
        }

        return trim($content);
    }
}
