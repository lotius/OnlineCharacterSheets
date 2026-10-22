<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsBestEffort;
use App\Models\GameSession;
use App\Models\StreamOverlay;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A data-free change notice. Clients compare revisions and fetch what they are allowed to see over HTTP.
 * When one character changed, the notice names it so clients refresh just that character.
 */
class RoomRealtimePinged implements ShouldBroadcastNow
{
    use BroadcastsBestEffort, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly GameSession $gameSession,
        public readonly bool $includePublicChannel = false,
        public readonly ?int $characterId = null,
        public readonly ?int $characterRevision = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("games.{$this->gameSession->id}")];

        if ($this->includePublicChannel || $this->gameSession->visibility === 'public_view') {
            $channels[] = new Channel("games.public.{$this->gameSession->id}");
        }

        // Overlay channel names are unguessable and rotate with the overlay token.
        foreach (StreamOverlay::query()->where('game_session_id', $this->gameSession->id)->pluck('channel_key') as $key) {
            $channels[] = new Channel("overlays.{$key}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'room.realtime.ping';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameSession->id,
            'room_name' => $this->gameSession->name,
            'revision' => $this->gameSession->current_revision,
            'visibility' => $this->gameSession->visibility,
            'character_id' => $this->characterId,
            'character_revision' => $this->characterRevision,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}
