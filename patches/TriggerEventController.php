<?php

namespace BeyondCode\LaravelWebSockets\HttpApi\Controllers;

use BeyondCode\LaravelWebSockets\Dashboard\DashboardLogger;
use BeyondCode\LaravelWebSockets\Facades\StatisticsLogger;
use Illuminate\Http\Request;

/**
 * Drop-in for beyondcode/laravel-websockets 1.14.
 * Symfony InputBag::get() rejects arrays ("channels"), so the body is read via all().
 * Applied by patches/apply-websockets-trigger.php after composer install.
 */
class TriggerEventController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->ensureValidSignature($request);

        $payload = $request->json()->all();

        if (array_key_exists('channel', $payload)) {
            $channels = [$payload['channel']];
        } else {
            $channels = $payload['channels'] ?? [];

            if (is_string($channels)) {
                $channels = [$channels];
            }

            if (! is_array($channels)) {
                $channels = [];
            }
        }

        $eventName = $payload['name'] ?? null;
        $eventData = $payload['data'] ?? null;
        $socketId = $payload['socket_id'] ?? null;

        foreach ($channels as $channelName) {
            if (! is_string($channelName) || $channelName === '') {
                continue;
            }

            $channel = $this->channelManager->find($request->appId, $channelName);

            optional($channel)->broadcastToEveryoneExcept([
                'channel' => $channelName,
                'event' => $eventName,
                'data' => $eventData,
            ], $socketId);

            DashboardLogger::apiMessage(
                $request->appId,
                $channelName,
                $eventName,
                $eventData
            );

            StatisticsLogger::apiMessage($request->appId);
        }

        return (object) [];
    }
}
