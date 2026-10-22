<?php

namespace App\Services;

use App\Models\Character;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Generic, data-driven item rules. Everything game specific lives in item definition `configuration`:
 *
 *   category        string   e.g. weapon, armor, potion, artifact (free text; the system may list known categories)
 *   cost            int      purchase price, display only
 *   modifiers       list     [{stat: key, op: add|set, value: number, when: equipped|carried}]
 *   allowed_slots   list     slot keys the item may occupy (one of them); empty = any slot
 *   required_slots  list     slot keys the item occupies all at once (two-handed weapons)
 *   exclude_classes list     class keys that may not use the item
 *   only_classes    list     class keys that may exclusively use the item
 *   consumable      bool     "Use" removes one from the stack
 *   tags            list     short labels (ranged, diagonal, throwable, ...)
 *   properties      object   free display fields, e.g. {"Range": "Line of sight"}
 */
class ItemRules
{
    public const RULE_MODES = ['tracker', 'warn', 'enforce'];

    /** @return array<string, mixed> */
    public function rules(?ItemDefinition $definition): array
    {
        $config = $definition?->configuration ?? [];

        return [
            'category' => is_string($config['category'] ?? null) ? $config['category'] : null,
            'cost' => is_numeric($config['cost'] ?? null) ? (int) $config['cost'] : null,
            'modifiers' => array_values(array_filter($config['modifiers'] ?? [], 'is_array')),
            'allowed_slots' => $this->keys($config['allowed_slots'] ?? []),
            'required_slots' => $this->keys($config['required_slots'] ?? []),
            'exclude_classes' => $this->keys($config['exclude_classes'] ?? []),
            'only_classes' => $this->keys($config['only_classes'] ?? []),
            'consumable' => (bool) ($config['consumable'] ?? false),
            'tags' => $this->keys($config['tags'] ?? []),
            'properties' => is_array($config['properties'] ?? null) ? array_map('strval', $config['properties']) : [],
        ];
    }

    /**
     * Validate item configuration supplied through the catalog or an import.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function validateConfiguration(array $config): array
    {
        $key = ['string', 'max:60', 'regex:/^[a-z0-9_-]+$/'];
        validator($config, [
            'category' => ['nullable', 'string', 'max:60'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'modifiers' => ['nullable', 'array', 'max:20'],
            'modifiers.*.stat' => ['required', ...$key],
            'modifiers.*.op' => ['required', Rule::in(['add', 'set'])],
            'modifiers.*.value' => ['required', 'numeric', 'between:-1000,1000'],
            'modifiers.*.when' => ['nullable', Rule::in(['equipped', 'carried'])],
            'allowed_slots' => ['nullable', 'array', 'max:20'], 'allowed_slots.*' => $key,
            'required_slots' => ['nullable', 'array', 'max:20'], 'required_slots.*' => $key,
            'exclude_classes' => ['nullable', 'array', 'max:50'], 'exclude_classes.*' => $key,
            'only_classes' => ['nullable', 'array', 'max:50'], 'only_classes.*' => $key,
            'consumable' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array', 'max:20'], 'tags.*' => $key,
            'properties' => ['nullable', 'array', 'max:20'],
            'properties.*' => ['nullable', 'string', 'max:200'],
        ], [], ['modifiers.*.stat' => 'modifier stat'])->validate();

        return $config;
    }

    /** A class restriction message, or null when the character may use the item. */
    public function restriction(Character $character, ?ItemDefinition $definition): ?string
    {
        $rules = $this->rules($definition);
        $class = $character->characterClass?->key;
        if ($class === null || ($rules['exclude_classes'] === [] && $rules['only_classes'] === [])) {
            return null;
        }
        $blocked = in_array($class, $rules['exclude_classes'], true)
            || ($rules['only_classes'] !== [] && ! in_array($class, $rules['only_classes'], true));

        return $blocked ? "{$character->characterClass->name} cannot normally use {$definition->name}." : null;
    }

    public function mode(GameSystemVersion $version): string
    {
        $mode = $version->configuration['rules_mode'] ?? 'warn';

        return in_array($mode, self::RULE_MODES, true) ? $mode : 'warn';
    }

    /**
     * Apply the system's rule mode to a restriction: enforce rejects, warn returns the message, tracker ignores it.
     *
     * @return list<string>
     */
    public function check(Character $character, ?ItemDefinition $definition): array
    {
        $message = $this->restriction($character, $definition);
        if ($message === null) {
            return [];
        }

        return match ($this->mode($character->gameSystemVersion)) {
            'enforce' => throw ValidationException::withMessages(['item' => $message]),
            'warn' => [$message],
            default => [],
        };
    }

    /** @return list<string> */
    private function keys(mixed $values): array
    {
        return array_values(array_filter(Arr::wrap($values), fn ($value) => is_string($value) && $value !== ''));
    }
}
