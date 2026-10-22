<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AddCharacterItem;
use App\Actions\Inventory\RemoveCharacterItem;
use App\Actions\Inventory\UpdateCharacterItemQuantity;
use App\Actions\Inventory\UseCharacterItem;
use App\Http\Requests\Inventory\StoreCharacterItemRequest;
use App\Http\Requests\Inventory\UpdateCharacterItemRequest;
use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterItemController extends Controller
{
    public function store(StoreCharacterItemRequest $request, Character $character, AddCharacterItem $add): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, $request->validated());

        return $this->characterResponse($request, $character, success: 'Item added.');
    }

    public function update(UpdateCharacterItemRequest $request, Character $character, CharacterItem $item, UpdateCharacterItemQuantity $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $item, [
            'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
            'delta' => $request->filled('delta') ? $request->integer('delta') : null,
        ]);

        return $this->characterResponse($request, $character);
    }

    public function use(Request $request, Character $character, CharacterItem $item, UseCharacterItem $use): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $use->handle($character, $item);

        return $this->characterResponse($request, $character);
    }

    public function destroy(Request $request, Character $character, CharacterItem $item, RemoveCharacterItem $remove): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $item);

        return $this->characterResponse($request, $character, success: 'Item removed.');
    }
}
