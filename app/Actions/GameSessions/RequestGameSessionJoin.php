<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use App\Models\User;

class RequestGameSessionJoin
{
    public function handle(User $user, GameSession $session): GameSessionMember
    {
        $membership = GameSessionMember::query()->firstOrNew([
            'game_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        if (! $membership->exists || in_array($membership->status, ['rejected', 'removed'], true)) {
            $membership->fill(['role' => 'player', 'status' => 'requested', 'approved_at' => null, 'joined_at' => null])->save();
        }

        return $membership;
    }
}
