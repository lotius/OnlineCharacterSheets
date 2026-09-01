<?php

namespace App\Actions\GameSessions;

use App\Events\PublicRoomDirectoryChanged;
use App\Events\RoomRealtimePinged;
use App\Models\Character;
use App\Models\GameSession;

class BroadcastGameSessionUpdate
{
    public function room(GameSession $game, bool $includePublicChannel = false, bool $includePublicDirectory = false): void
    {
        $game->increment('current_revision');
        $game->refresh();

        RoomRealtimePinged::dispatch($game, $includePublicChannel);

        if ($includePublicDirectory) {
            PublicRoomDirectoryChanged::dispatch();
        }
    }

    public function character(Character $character): void
    {
        $character->sessions()
            ->where('is_active', true)
            ->with('gameSession')
            ->get()
            ->each(fn ($sessionCharacter) => $this->room($sessionCharacter->gameSession));
    }

    public function publicDirectory(): void
    {
        PublicRoomDirectoryChanged::dispatch();
    }
}
