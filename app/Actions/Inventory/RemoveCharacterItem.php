<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class RemoveCharacterItem
{
    public function handle(Character $character, CharacterItem $item): void
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
        app(CharacterMutation::class)->run($character, 'item.removed', 'item', $item->id, fn () => $item->delete());
    }
}
