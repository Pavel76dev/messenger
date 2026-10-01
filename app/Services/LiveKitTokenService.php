<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class LiveKitTokenService
{
    public function createToken(User $user, string $roomName, bool $canPublish = true): string
    {
        $apiKey = (string) config('livekit.api_key');
        $apiSecret = (string) config('livekit.api_secret');

        if ($apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('LiveKit API keys are not configured.');
        }

        $now = time();
        $ttl = max(60, (int) config('livekit.token_ttl_seconds', 3600));

        $claims = [
            'iss' => $apiKey,
            'sub' => 'user-'.$user->id,
            'nbf' => $now - 10,
            'exp' => $now + $ttl,
            'name' => $user->name,
            'video' => [
                'roomJoin' => true,
                'room' => $roomName,
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        return $this->encodeJwt($claims, $apiSecret);
    }

    public function wsUrl(): string
    {
        return (string) config('livekit.url');
    }

    private function encodeJwt(array $claims, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header.'.'.$payload, $secret, true)
        );

        return $header.'.'.$payload.'.'.$signature;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
