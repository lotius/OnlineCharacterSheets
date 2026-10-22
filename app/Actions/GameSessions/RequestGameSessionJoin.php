<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use App\Models\User;
use App\Services\RoomMutation;

class RequestGameSessionJoin
{
    public function handle(User $user, GameSession $session): GameSessionMember
    {
        return app(RoomMutation::class)->run($session, 'member.requested', fn () => $session->members()->where('user_id', $user->id)->first()?->only(['id', 'role', 'status']) ?? [], function () use ($user, $session) {
            $membership = GameSessionMember::query()->firstOrNew([
                'game_session_id' => $session->id,
                'user_id' => $user->id,
            ]);

            if (! $membership->exists || in_array($membership->status, ['rejected', 'removed'], true)) {
                $membership->fill(['role' => 'player', 'status' => 'requested', 'approved_at' => null, 'joined_at' => null])->save();
            }

            return $membership;
        }, $user->id, authorize: false);
    }
}
