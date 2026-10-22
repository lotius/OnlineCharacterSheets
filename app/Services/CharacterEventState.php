<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\CharacterNote;
use App\Models\CharacterSkill;
use App\Models\CharacterSpell;
use App\Models\CharacterStat;
use App\Models\SlotDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CharacterEventState
{
    /** @param array<string, mixed> $left
     * @param  array<string, mixed>  $right
     */
    public function same(array $left, array $right): bool
    {
        $sort = function (array $value) use (&$sort): array {
            ksort($value);
            foreach ($value as &$entry) {
                if (is_array($entry)) {
                    $entry = $sort($entry);
                }
            }

            return $value;
        };

        return $sort($left) === $sort($right);
    }

    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'stat' => CharacterStat::class, 'item' => CharacterItem::class,
        'spell' => CharacterSpell::class, 'skill' => CharacterSkill::class,
        'note' => CharacterNote::class,
    ];

    /** @return array<string, mixed> */
    public function read(Character $character, string $type, ?int $id): array
    {
        if ($type === 'gold') {
            return ['gold' => $character->fresh()->gold];
        }
        $model = self::MODELS[$type] ?? throw new \InvalidArgumentException('Unsupported event entity.');
        $query = $model::query()->where('character_id', $character->id);
        $entity = $type === 'note' ? $query->first() : $query->whereKey($id)->first();
        if (! $entity) {
            return [];
        }
        $state = $entity->attributesToArray();
        unset($state['created_at'], $state['updated_at']);
        if ($entity instanceof CharacterItem) {
            $state['slots'] = $entity->slots()->orderBy('slot_definition_id')->pluck('slot_definition_id')->all();
        }

        return $state;
    }

    /** @param array<string, mixed> $state */
    public function restore(Character $character, string $type, int $id, array $state): void
    {
        if ($type === 'gold') {
            $character->update(['gold' => $state['gold']]);

            return;
        }
        $model = self::MODELS[$type] ?? throw new \InvalidArgumentException('Unsupported event entity.');
        $entity = $model::query()->where('character_id', $character->id)->whereKey($type === 'note' ? ($state['id'] ?? $character->note?->id) : $id)->first();
        if ($state === []) {
            $entity?->delete();

            return;
        }
        if ($type === 'stat') {
            $stat = CharacterStat::query()->findOrFail($id);
            $definition = $stat->definition;
            $minimum = $definition->minimum_value;
            $maximum = $definition->maximum_value ?? $stat->max_value;
            if (($minimum !== null && $state['value'] < $minimum) || ($maximum !== null && $state['value'] > $maximum)) {
                throw ValidationException::withMessages(['event' => 'The previous value is outside the current stat limits.']);
            }
        }
        $slots = $state['slots'] ?? [];
        unset($state['slots']);
        // Never overwrite a different record or bypass a changed definition/slot capacity.
        if (! $entity && $model::query()->whereKey($state['id'])->exists()) {
            throw ValidationException::withMessages(['event' => 'The original record cannot be restored safely.']);
        }
        $definitionColumn = match ($type) {
            'item' => 'item_definition_id', 'spell' => 'spell_definition_id', 'skill' => 'skill_definition_id', default => null,
        };
        if ($definitionColumn && $state[$definitionColumn] !== null) {
            $candidate = new $model($state);
            $definition = $candidate->definition()->first();
            $ownerAllowed = ! $definition?->getAttribute('owner_user_id') || $character->sessions()->whereHas('gameSession', fn ($query) => $query->where('owner_user_id', $definition->getAttribute('owner_user_id')))->exists();
            if (! $definition || $definition->getAttribute('game_system_version_id') !== $character->game_system_version_id || ! $ownerAllowed) {
                throw ValidationException::withMessages(['event' => 'The original definition no longer exists.']);
            }
            if ($type !== 'item' && $model::query()->where('character_id', $character->id)->where($definitionColumn, $state[$definitionColumn])->where('id', '!=', $state['id'])->exists()) {
                throw ValidationException::withMessages(['event' => 'That ability has already been added again.']);
            }
        }
        foreach ($slots as $slotId) {
            $slot = SlotDefinition::query()->find($slotId);
            $occupied = $character->items()->where('id', '!=', $id)->whereHas('slots', fn ($query) => $query->where('slot_definition_id', $slotId))->count();
            if (! $slot || $slot->game_system_version_id !== $character->game_system_version_id || $occupied >= $slot->capacity) {
                throw ValidationException::withMessages(['event' => 'The previous equipment slots are no longer available.']);
            }
        }
        $entity ??= new $model;
        $entity->fill($state)->save();
        if ($entity instanceof CharacterItem) {
            $entity->slots()->delete();
            foreach ($slots as $slotId) {
                $entity->slots()->create(['slot_definition_id' => $slotId]);
            }
        }
    }
}
