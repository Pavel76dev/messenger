<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallIncoming implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<PrivateChannel|string>  $channels
     */
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
        return 'call.incoming';
    }

    public function broadcastWith(): array
    {
        return ['call' => $this->call];
    }
}
