<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\ItemDefinition;
use App\Services\CharacterMutation;
use App\Services\ItemRules;
use Illuminate\Validation\ValidationException;

class AddCharacterItem
{
    /** @param array{item_definition_id?: int|null, custom_name?: string|null, custom_description?: string|null, quantity: int} $attributes */
    public function handle(Character $character, array $attributes): CharacterItem
    {
        $definition = empty($attributes['item_definition_id']) ? null : ItemDefinition::query()->findOrFail($attributes['item_definition_id']);

        $ownerAllowed = ! $definition?->owner_user_id || $character->sessions()
            ->whereHas('gameSession', fn ($query) => $query->where('owner_user_id', $definition->owner_user_id))->exists();

        if ($definition && ($definition->game_system_version_id !== $character->game_system_version_id || ! $ownerAllowed)) {
            throw ValidationException::withMessages(['item_definition_id' => 'That item does not belong to this character’s game system.']);
        }

        if (! $definition && blank($attributes['custom_name'] ?? null)) {
            throw ValidationException::withMessages(['custom_name' => 'Choose an item or provide a custom item name.']);
        }

        // Consumables stack onto an existing, uncustomised stack instead of creating another row.
        $stack = $definition && app(ItemRules::class)->rules($definition)['consumable'] && blank($attributes['custom_name'] ?? null)
            ? $character->items()->where('item_definition_id', $definition->id)->whereNull('custom_name')->orderBy('id')->first()
            : null;
        if ($stack) {
            app(UpdateCharacterItemQuantity::class)->handle($character, $stack, ['delta' => (int) $attributes['quantity']]);

            return $stack->refresh();
        }

        return app(CharacterMutation::class)->run($character, 'item.added', 'item', null, fn () => $character->items()->create($attributes));
    }
}
