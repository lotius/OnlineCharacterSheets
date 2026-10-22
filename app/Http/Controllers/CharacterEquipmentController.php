<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\EquipCharacterItem;
use App\Actions\Inventory\UnequipCharacterItem;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\SlotDefinition;
use Illuminate\Http\RedirectResponse;

class CharacterEquipmentController extends Controller
{
    public function update(Character $character, CharacterItem $item, SlotDefinition $slot, EquipCharacterItem $equip): RedirectResponse
    {
        $this->authorize('update', $character);
        $equip->handle($character, $item, $slot);

        return back()->with('success', 'Item equipped.');
    }

    public function destroy(Character $character, CharacterItem $item, SlotDefinition $slot, UnequipCharacterItem $unequip): RedirectResponse
    {
        $this->authorize('update', $character);
        $unequip->handle($character, $item, $slot);

        return back()->with('success', 'Item unequipped.');
    }
}
