<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use Illuminate\Validation\ValidationException;

class RemoveGameSessionMember
{
    public function handle(GameSession $session, GameSessionMember $member): GameSessionMember
    {
        if ($member->game_session_id !== $session->id || $member->role === 'dm') {
            throw ValidationException::withMessages(['member' => 'This membership cannot be removed from this room.']);
        }

        $member->update(['status' => 'removed', 'approved_at' => null]);

        return $member->refresh();
    }
}
