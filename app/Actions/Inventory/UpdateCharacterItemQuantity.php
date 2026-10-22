<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UpdateCharacterItemQuantity
{
    /** @param array{quantity?: int|null, delta?: int|null} $change */
    public function handle(Character $character, CharacterItem $item, array $change): CharacterItem
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
        app(CharacterMutation::class)->run($character, 'item.quantity_changed', 'item', $item->id, function () use ($item, $change) {
            $item->refresh();
            $quantity = isset($change['delta']) ? max(1, min(999, $item->quantity + $change['delta'])) : (int) $change['quantity'];
            $item->update(['quantity' => $quantity]);
        });

        return $item->refresh();
    }
}
