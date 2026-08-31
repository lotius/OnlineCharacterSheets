<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\SlotDefinition;
use Illuminate\Validation\ValidationException;

class UnequipCharacterItem
{
    public function handle(Character $character, CharacterItem $item, SlotDefinition $slot): void
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
        $item->slots()->where('slot_definition_id', $slot->id)->delete();
    }
}
