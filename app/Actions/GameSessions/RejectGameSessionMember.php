<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSessionMember;
use App\Services\RoomMutation;
use Illuminate\Validation\ValidationException;

class RejectGameSessionMember
{
    public function handle(GameSession $session, GameSessionMember $member): GameSessionMember
    {
        if ($member->game_session_id !== $session->id || $member->role === 'dm') {
            throw ValidationException::withMessages(['member' => 'This membership cannot be rejected for this room.']);
        }

        app(RoomMutation::class)->run($session, 'member.rejected', fn () => $member->fresh()->only(['id', 'user_id', 'role', 'status']), function () use ($member) {
            $member->refresh()->update(['status' => 'rejected', 'approved_at' => null, 'joined_at' => null]);
        });

        return $member->refresh();
    }
}
