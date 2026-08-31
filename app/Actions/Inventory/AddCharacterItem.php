<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\ItemDefinition;
use Illuminate\Validation\ValidationException;

class AddCharacterItem
{
    /** @param array{item_definition_id?: int|null, custom_name?: string|null, custom_description?: string|null, quantity: int} $attributes */
    public function handle(Character $character, array $attributes): CharacterItem
    {
        $definition = empty($attributes['item_definition_id']) ? null : ItemDefinition::query()->findOrFail($attributes['item_definition_id']);

        if ($definition && $definition->game_system_version_id !== $character->game_system_version_id) {
            throw ValidationException::withMessages(['item_definition_id' => 'That item does not belong to this character’s game system.']);
        }

        if (! $definition && blank($attributes['custom_name'] ?? null)) {
            throw ValidationException::withMessages(['custom_name' => 'Choose an item or provide a custom item name.']);
        }

        return $character->items()->create($attributes);
    }
}
