<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicRoomDirectoryChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('rooms.public');
    }

    public function broadcastAs(): string
    {
        return 'public.rooms.changed';
    }

    /** @return array{sent_at: string} */
    public function broadcastWith(): array
    {
        return ['sent_at' => now()->toIso8601String()];
    }
}
