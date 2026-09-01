<?php

namespace App\Actions\Characters;

use App\Models\GameSession;
use App\Models\SessionCharacter;
use Illuminate\Validation\ValidationException;

class RemoveCharacterFromGameSession
{
    public function handle(GameSession $session, SessionCharacter $sessionCharacter): SessionCharacter
    {
        if ($sessionCharacter->game_session_id !== $session->id) {
            throw ValidationException::withMessages(['character' => 'That character does not belong to this room.']);
        }

        $sessionCharacter->update([
            'assigned_user_id' => null,
            'is_active' => false,
        ]);

        return $sessionCharacter->refresh();
    }
}
