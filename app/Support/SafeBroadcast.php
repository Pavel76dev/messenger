<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function toOthers(ShouldBroadcast $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (Throwable $exception) {
            Log::warning('Broadcast failed: '.$exception->getMessage(), [
                'event' => $event::class,
            ]);
        }
    }

    public static function now(ShouldBroadcast $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Broadcast failed: '.$exception->getMessage(), [
                'event' => $event::class,
            ]);
        }
    }
}
