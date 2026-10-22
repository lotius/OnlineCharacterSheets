<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\SlotDefinition;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UnequipCharacterItem
{
    /** Remove the item from one slot, or from every slot when none is given. */
    public function handle(Character $character, CharacterItem $item, ?SlotDefinition $slot = null): void
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
        app(CharacterMutation::class)->run($character, 'item.unequipped', 'item', $item->id, fn () => $item->slots()
            ->when($slot, fn ($query) => $query->where('slot_definition_id', $slot->id))->delete());
    }
}
