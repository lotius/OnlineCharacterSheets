<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomRealtimePinged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GameSession $gameSession,
        public readonly bool $includePublicChannel = false,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("games.{$this->gameSession->id}")];

        if ($this->includePublicChannel || $this->gameSession->visibility === 'public_view') {
            $channels[] = new Channel("games.public.{$this->gameSession->id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'room.realtime.ping';
    }

    /** @return array{game_id: int, room_name: string, revision: int, visibility: string, sent_at: string} */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameSession->id,
            'room_name' => $this->gameSession->name,
            'revision' => $this->gameSession->current_revision,
            'visibility' => $this->gameSession->visibility,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
