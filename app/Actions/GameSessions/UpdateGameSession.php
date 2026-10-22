<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Services\RoomMutation;

class UpdateGameSession
{
    /** @param array{name: string, visibility: string} $attributes */
    public function handle(GameSession $game, array $attributes): void
    {
        app(RoomMutation::class)->run($game, 'session.updated', fn () => $game->fresh()->only(['name', 'visibility']), fn (GameSession $locked) => $locked->update($attributes));
    }
}
