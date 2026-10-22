<?php

namespace App\Actions\Characters;

use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Services\RoomMutation;
use Illuminate\Validation\ValidationException;

class UnassignCharacter
{
    public function handle(GameSession $session, SessionCharacter $sessionCharacter): SessionCharacter
    {
        if ($sessionCharacter->game_session_id !== $session->id) {
            throw ValidationException::withMessages(['character' => 'That character does not belong to this room.']);
        }

        app(RoomMutation::class)->run($session, 'character.unassigned', fn () => $sessionCharacter->fresh()->only(['id', 'character_id', 'assigned_user_id', 'is_active']), function () use ($sessionCharacter) {
            if (! $sessionCharacter->fresh()->is_active) {
                throw ValidationException::withMessages(['character' => 'This character is no longer active in the room.']);
            }
            $sessionCharacter->refresh()->update(['assigned_user_id' => null]);
        });

        return $sessionCharacter->refresh();
    }
}
