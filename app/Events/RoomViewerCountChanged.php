<?php

namespace App\Events;

use App\Models\GameSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomViewerCountChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GameSession $gameSession,
        public readonly int $viewerCount,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("games.{$this->gameSession->id}")];

        if ($this->gameSession->visibility === 'public_view') {
            $channels[] = new Channel("games.public.{$this->gameSession->id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'room.viewer_count.changed';
    }

    /** @return array{game_id: int, viewer_count: int, sent_at: string} */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameSession->id,
            'viewer_count' => $this->viewerCount,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
