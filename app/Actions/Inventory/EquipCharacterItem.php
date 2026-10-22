<?php

namespace App\Actions\Inventory;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterItemSlot;
use App\Models\SlotDefinition;
use App\Services\CharacterMutation;
use App\Services\ItemRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EquipCharacterItem
{
    public function __construct(private ItemRules $rules, private UnequipCharacterItem $unequip) {}

    /**
     * Equip into the requested slot, or pick one from the item's rules when no slot is given.
     * Items already occupying the target slots are unequipped first (each as its own audited event),
     * so swapping a weapon is a single tap.
     *
     * @return list<string> rule warnings (warn mode)
     */
    public function handle(Character $character, CharacterItem $item, ?SlotDefinition $slot = null): array
    {
        if ($item->character_id !== $character->id) {
            throw ValidationException::withMessages(['slot' => 'That item is not valid for this character.']);
        }
        $character->loadMissing('characterClass', 'gameSystemVersion');
        $warnings = $this->rules->check($character, $item->definition);

        DB::transaction(function () use ($character, $item, $slot) {
            $targets = $this->targets($character, $item, $slot);
            foreach ($this->occupants($character, $item, $targets) as $occupant) {
                $this->unequip->handle($character, $occupant);
            }

            app(CharacterMutation::class)->run($character, 'item.equipped', 'item', $item->id, function () use ($item, $targets) {
                $item->slots()->whereNotIn('slot_definition_id', $targets->pluck('id'))->delete();
                foreach ($targets as $target) {
                    CharacterItemSlot::query()->firstOrCreate(['character_item_id' => $item->id, 'slot_definition_id' => $target->id]);
                }
            });
        });

        return $warnings;
    }

    /**
     * The slots this item would occupy (without changing anything).
     *
     * @return Collection<int, SlotDefinition>
     */
    public function targets(Character $character, CharacterItem $item, ?SlotDefinition $slot): Collection
    {
        $rules = $this->rules->rules($item->definition);
        $slots = SlotDefinition::query()->where('game_system_version_id', $character->game_system_version_id)
            ->where(fn ($query) => $query->whereNull('owner_user_id')->orWhereIn('owner_user_id', $character->sessions()->join('game_sessions', 'game_sessions.id', '=', 'session_characters.game_session_id')->select('game_sessions.owner_user_id')))
            ->orderBy('display_order')->get();

        if ($slot && ! $slots->contains('id', $slot->id)) {
            throw ValidationException::withMessages(['slot' => 'That slot is not valid for this character.']);
        }

        if ($rules['required_slots'] !== []) {
            $targets = $slots->whereIn('key', $rules['required_slots'])->values();
            if ($targets->count() !== count($rules['required_slots'])) {
                throw ValidationException::withMessages(['slot' => 'This item needs slots this game system does not define.']);
            }

            return $targets;
        }

        // allowed_slots is a preference order (e.g. main hand before off hand).
        $allowed = $rules['allowed_slots'] === [] ? $slots : collect($rules['allowed_slots'])
            ->map(fn (string $key) => $slots->firstWhere('key', $key))->filter()->values();
        if ($slot) {
            if (! $allowed->contains('id', $slot->id)) {
                throw ValidationException::withMessages(['slot' => "{$item->definition?->name} cannot go in {$slot->name}."]);
            }

            return collect([$slot]);
        }
        if ($allowed->isEmpty()) {
            throw ValidationException::withMessages(['slot' => 'This item has no slot it can be equipped to.']);
        }

        // Prefer the first allowed slot with room; otherwise swap out whatever is in the first allowed slot.
        $free = $allowed->first(fn (SlotDefinition $candidate) => $this->used($character, $item, $candidate) < $candidate->capacity);

        return collect([$free ?? $allowed->first()]);
    }

    /**
     * Items that must leave the target slots for this item to fit.
     *
     * @param  Collection<int, SlotDefinition>  $targets
     * @return Collection<int, CharacterItem>
     */
    private function occupants(Character $character, CharacterItem $item, Collection $targets): Collection
    {
        $occupants = collect();
        foreach ($targets as $target) {
            $inSlot = $character->items()->where('id', '!=', $item->id)
                ->whereHas('slots', fn ($query) => $query->where('slot_definition_id', $target->id))->orderBy('id')->get();
            $excess = $inSlot->count() - ($target->capacity - 1);
            if ($excess > 0) {
                $occupants = $occupants->merge($inSlot->take($excess));
            }
        }

        return $occupants->unique('id')->values();
    }

    private function used(Character $character, CharacterItem $item, SlotDefinition $slot): int
    {
        return CharacterItemSlot::query()->where('slot_definition_id', $slot->id)->where('character_item_id', '!=', $item->id)
            ->whereHas('characterItem', fn ($query) => $query->where('character_id', $character->id))->count();
    }
}
