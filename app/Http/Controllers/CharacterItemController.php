<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AddCharacterItem;
use App\Actions\Inventory\RemoveCharacterItem;
use App\Actions\Inventory\UpdateCharacterItemQuantity;
use App\Http\Requests\Inventory\StoreCharacterItemRequest;
use App\Http\Requests\Inventory\UpdateCharacterItemRequest;
use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Http\RedirectResponse;

class CharacterItemController extends Controller
{
    public function store(StoreCharacterItemRequest $request, Character $character, AddCharacterItem $add): RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, $request->validated());

        return back()->with('success', 'Item added.');
    }

    public function update(UpdateCharacterItemRequest $request, Character $character, CharacterItem $item, UpdateCharacterItemQuantity $update): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $item, $request->integer('quantity'));

        return back();
    }

    public function destroy(Character $character, CharacterItem $item, RemoveCharacterItem $remove): RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $item);

        return back()->with('success', 'Item removed.');
    }
}
