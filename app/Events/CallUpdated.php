<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $call,
        public array $channels,
    ) {
    }

    public function broadcastOn(): array
    {
        return array_map(
            fn ($channel) => $channel instanceof PrivateChannel
                ? $channel
                : new PrivateChannel($channel),
            $this->channels
        );
    }

    public function broadcastAs(): string
    {
        return 'call.updated';
    }

    public function broadcastWith(): array
    {
        return ['call' => $this->call];
    }
}
