<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use App\Models\User;

class RequestGameSessionJoin
{
    public function handle(User $user, GameSession $session): GameSessionMember
    {
        return $session->members()->firstOrCreate(
            ['user_id' => $user->id],
            ['role' => 'player', 'status' => 'requested'],
        );
    }
}
