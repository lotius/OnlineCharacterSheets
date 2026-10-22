<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Services\CharacterMutation;

class UpdateCharacterGold
{
    public function handle(Character $character, int $gold): void
    {
        app(CharacterMutation::class)->run($character, 'gold.changed', 'gold', $character->id, fn () => $character->refresh()->update(['gold' => $gold]));
    }
}
