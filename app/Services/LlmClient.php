<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
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

        [$target, $model] = $this->resolveTargetAndModel($base);

        $payload = [
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => 1024,
            'target' => $target,
        ];

        if (is_string($model) && $model !== '') {
            $payload['model'] = $model;
        }

        $response = $this->http()
            ->timeout((int) config('llm.timeout_sec', 120))
            ->post("{$base}/api/chat", $payload);

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

    /**
     * @return array{0: string, 1: string|null}
     */
    private function resolveTargetAndModel(string $base): array
    {
        $configuredTarget = strtolower(trim((string) config('llm.target', 'auto')));
        $configuredModel = config('llm.model');
        $model = is_string($configuredModel) && $configuredModel !== '' ? $configuredModel : null;

        if (in_array($configuredTarget, ['local', 'remote'], true)) {
            return [$configuredTarget, $model];
        }

        // auto: спросить health и выбрать reachable бэкенд
        try {
            $health = $this->http()->timeout(15)->get("{$base}/api/health");
        } catch (\Throwable $exception) {
            throw new RuntimeException('api-llm недоступен: '.$exception->getMessage(), 0, $exception);
        }

        if (! $health->successful()) {
            throw new RuntimeException('api-llm /api/health HTTP '.$health->status());
        }

        $data = $health->json() ?: [];
        $backends = is_array($data['backends'] ?? null) ? $data['backends'] : [];
        $default = strtolower((string) ($data['default_target'] ?? 'local'));

        $order = [];
        if (in_array($default, ['local', 'remote'], true)) {
            $order[] = $default;
        }
        foreach (['local', 'remote'] as $name) {
            if (! in_array($name, $order, true)) {
                $order[] = $name;
            }
        }

        foreach ($order as $name) {
            $info = $backends[$name] ?? null;
            if (! is_array($info) || empty($info['reachable'])) {
                continue;
            }
            if ($model === null) {
                $fromHealth = $info['default_model'] ?? null;
                if (is_string($fromHealth) && $fromHealth !== '') {
                    $model = $fromHealth;
                } elseif (! empty($info['models'][0]) && is_string($info['models'][0])) {
                    $model = $info['models'][0];
                }
            }

            return [$name, $model];
        }

        throw new RuntimeException(
            'api-llm: ни local, ни remote не reachable. Проверьте Ollama/LM Studio и LLM_REMOTE_BASE_URL.'
        );
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()->asJson();
        $token = config('llm.api_token');
        if (is_string($token) && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
