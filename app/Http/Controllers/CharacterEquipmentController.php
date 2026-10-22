<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\EquipCharacterItem;
use App\Actions\Inventory\UnequipCharacterItem;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\SlotDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterEquipmentController extends Controller
{
    /** Equip into a chosen slot (anything in the way is swapped out). */
    public function update(Request $request, Character $character, CharacterItem $item, SlotDefinition $slot, EquipCharacterItem $equip): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);

        return $this->characterResponse($request, $character, $equip->handle($character, $item, $slot), 'Item equipped.');
    }

    /** One-tap equip: the item's rules choose the slot(s). */
    public function equip(Request $request, Character $character, CharacterItem $item, EquipCharacterItem $equip): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);

        return $this->characterResponse($request, $character, $equip->handle($character, $item), 'Item equipped.');
    }

    public function destroy(Request $request, Character $character, CharacterItem $item, SlotDefinition $slot, UnequipCharacterItem $unequip): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $unequip->handle($character, $item, $slot);

        return $this->characterResponse($request, $character, success: 'Item unequipped.');
    }

    public function unequip(Request $request, Character $character, CharacterItem $item, UnequipCharacterItem $unequip): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $unequip->handle($character, $item);

        return $this->characterResponse($request, $character, success: 'Item unequipped.');
    }
}
