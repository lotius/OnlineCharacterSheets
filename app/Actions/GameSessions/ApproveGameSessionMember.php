<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use Illuminate\Validation\ValidationException;

class ApproveGameSessionMember
{
    public function handle(GameSession $session, GameSessionMember $member): GameSessionMember
    {
        $this->ensureMemberBelongsToSession($session, $member);

        $member->update([
            'status' => 'active',
            'approved_at' => now(),
            'joined_at' => $member->joined_at ?? now(),
        ]);

        return $member->refresh();
    }

    private function ensureMemberBelongsToSession(GameSession $session, GameSessionMember $member): void
    {
        if ($member->game_session_id !== $session->id || $member->role === 'dm') {
            throw ValidationException::withMessages(['member' => 'This membership cannot be approved for this room.']);
        }
    }
}
