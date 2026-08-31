<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterItemSlot;
use App\Models\SlotDefinition;
use Illuminate\Validation\ValidationException;

class EquipCharacterItem
{
    public function handle(Character $character, CharacterItem $item, SlotDefinition $slot): CharacterItemSlot
    {
        if ($item->character_id !== $character->id || $slot->game_system_version_id !== $character->game_system_version_id) {
            throw ValidationException::withMessages(['slot' => 'That item or slot is not valid for this character.']);
        }

        $occupied = CharacterItemSlot::query()->where('slot_definition_id', $slot->id)
            ->whereHas('characterItem', fn ($query) => $query->where('character_id', $character->id))
            ->where('character_item_id', '!=', $item->id)->count();

        if ($occupied >= $slot->capacity) {
            throw ValidationException::withMessages(['slot' => 'That equipment slot is full.']);
        }

        return CharacterItemSlot::query()->firstOrCreate(['character_item_id' => $item->id, 'slot_definition_id' => $slot->id]);
    }
}
