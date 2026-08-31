<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use Illuminate\Validation\ValidationException;

class RejectGameSessionMember
{
    public function handle(GameSession $session, GameSessionMember $member): GameSessionMember
    {
        if ($member->game_session_id !== $session->id || $member->role === 'dm') {
            throw ValidationException::withMessages(['member' => 'This membership cannot be rejected for this room.']);
        }

        $member->update(['status' => 'rejected', 'approved_at' => null, 'joined_at' => null]);

        return $member->refresh();
    }
}
