<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class SafeBroadcast
{
    public static function toOthers(ShouldBroadcast $event): void
    {
        self::run(fn () => broadcast($event)->toOthers(), $event);
    }

    public static function now(ShouldBroadcast $event): void
    {
        // После ответа клиенту — accept/end не ждут 30с таймаут Pusher
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            self::run(fn () => broadcast($event), $event);

            return;
        }

        dispatch(function () use ($event) {
            self::run(fn () => broadcast($event), $event);
        })->afterResponse();
    }

    private static function run(callable $callback, ShouldBroadcast $event): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Broadcast failed: '.$exception->getMessage(), [
                'event' => $event::class,
            ]);
        }
    }
}
