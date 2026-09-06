<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\BroadcastGameSessionUpdate;
use App\Actions\Inventory\AddCharacterItem;
use App\Actions\Inventory\RemoveCharacterItem;
use App\Actions\Inventory\UpdateCharacterItemQuantity;
use App\Http\Requests\Inventory\StoreCharacterItemRequest;
use App\Http\Requests\Inventory\UpdateCharacterItemRequest;
use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CharacterItemController extends Controller
{
    public function store(StoreCharacterItemRequest $request, Character $character, AddCharacterItem $add, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, $request->validated());
        $broadcast->character($character);

        return back()->with('success', 'Item added.');
    }

    public function update(UpdateCharacterItemRequest $request, Character $character, CharacterItem $item, UpdateCharacterItemQuantity $update, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $item, $request->integer('quantity'));
        $broadcast->character($character);

        return back();
    }

    public function destroy(Character $character, CharacterItem $item, RemoveCharacterItem $remove, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $item);
        $broadcast->character($character);

        return back()->with('success', 'Item removed.');
    }

    public function updateActive(Request $request, Character $character, CharacterItem $item, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $character);

        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $item->update(['is_active' => $validated['is_active']]);
        $broadcast->character($character);

        return back();
    }
}
