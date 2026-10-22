<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Services\RoomMutation;
use Illuminate\Validation\ValidationException;

class UpdateTurnOrder
{
    /** @param list<int> $characterIds */
    public function handle(GameSession $game, array $characterIds): void
    {
        app(RoomMutation::class)->run($game, 'turn.order_changed', fn () => ['order' => $game->sessionCharacters()->where('is_active', true)->orderByRaw('turn_order IS NULL')->orderBy('turn_order')->orderBy('id')->pluck('character_id')->all()], function (GameSession $locked) use ($characterIds) {
            $expected = $locked->sessionCharacters()->where('is_active', true)->pluck('character_id')->sort()->values()->all();
            $received = $characterIds;
            sort($received);
            if ($received !== $expected) {
                throw ValidationException::withMessages(['character_ids' => 'Include every active character exactly once. The party may have changed; reload and try again.']);
            }
            foreach ($characterIds as $position => $characterId) {
                $locked->sessionCharacters()->where('character_id', $characterId)->update(['turn_order' => $position]);
            }
        });
    }
}
