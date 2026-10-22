<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UseCharacterItem
{
    /** Consume one from the stack; the last one removes the item. Undo restores it. */
    public function handle(Character $character, CharacterItem $item): void
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['item' => 'That item does not belong to this character.']);
        }
        app(CharacterMutation::class)->run($character, 'item.used', 'item', $item->id, function () use ($item) {
            $item->refresh();
            $item->quantity > 1 ? $item->decrement('quantity') : $item->delete();
        });
    }
}
