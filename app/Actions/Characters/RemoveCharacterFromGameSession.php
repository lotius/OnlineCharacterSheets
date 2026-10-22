<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Services\RoomMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveCharacterFromGameSession
{
    public function handle(GameSession $session, SessionCharacter $sessionCharacter): SessionCharacter
    {
        if ($sessionCharacter->game_session_id !== $session->id) {
            throw ValidationException::withMessages(['character' => 'That character does not belong to this room.']);
        }
        DB::transaction(function () use ($session, $sessionCharacter) {
            Character::query()->lockForUpdate()->findOrFail($sessionCharacter->character_id);
            $mutations = app(RoomMutation::class);
            $mutations->run($session, 'character.removed', fn () => $sessionCharacter->fresh()->only(['id', 'character_id', 'assigned_user_id', 'is_active']), function (GameSession $locked) use ($sessionCharacter, $mutations) {
                $sessionCharacter->refresh()->update(['assigned_user_id' => null, 'is_active' => false]);
                if ($locked->current_character_id === $sessionCharacter->character_id) {
                    $before = ['current_character_id' => $locked->current_character_id];
                    $locked->update(['current_character_id' => null]);
                    $mutations->record($locked, 'turn.cleared', $before, ['current_character_id' => null], auth()->id());
                }
            });
        });

        return $sessionCharacter->refresh();
    }
}
