<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UpdateCharacterItemQuantity
{
    public function handle(Character $character, CharacterItem $item, int $quantity): CharacterItem
    {
        $this->ensureOwned($character, $item);
        app(CharacterMutation::class)->run($character, 'item.quantity_changed', 'item', $item->id, fn () => $item->refresh()->update(['quantity' => $quantity]));

        return $item->refresh();
    }

    private function ensureOwned(Character $character, CharacterItem $item): void
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
    }
}
