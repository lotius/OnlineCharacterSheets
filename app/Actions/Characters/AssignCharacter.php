<?php

namespace App\Actions\Characters;

use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Models\User;
use App\Services\RoomMutation;
use Illuminate\Validation\ValidationException;

class AssignCharacter
{
    public function handle(GameSession $session, SessionCharacter $sessionCharacter, User $player): SessionCharacter
    {
        if ($sessionCharacter->game_session_id !== $session->id) {
            throw ValidationException::withMessages(['character' => 'That character does not belong to this room.']);
        }

        app(RoomMutation::class)->run($session, 'character.assigned', fn () => $sessionCharacter->fresh()->only(['id', 'character_id', 'assigned_user_id', 'is_active']), function () use ($session, $sessionCharacter, $player) {
            if (! $sessionCharacter->fresh()->is_active) {
                throw ValidationException::withMessages(['character' => 'This character is no longer active in the room.']);
            }
            $isActivePlayer = $session->members()->where('user_id', $player->id)->where('status', 'active')->whereIn('role', ['player', 'co_dm'])->exists();

            if (! $isActivePlayer) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Characters may only be assigned to active room players.']);
            }

            $sessionCharacter->refresh()->update(['assigned_user_id' => $player->id]);
        });

        return $sessionCharacter->refresh();
    }
}
