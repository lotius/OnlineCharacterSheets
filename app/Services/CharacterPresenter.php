<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\GameSession;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\StatDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that turns a character into JSON. Every surface (room cards, phone sheet,
 * spectators, overlays, mutation responses) uses this shape; audiences only remove fields.
 */
class CharacterPresenter
{
    public const RELATIONS = [
        'characterClass', 'stats.definition', 'items.definition', 'items.slots.definition',
        'spells.definition', 'skills.definition', 'note',
    ];

    public function __construct(private ItemRules $rules, private IconLibrary $icons) {}

    /**
     * @param  bool  $isMember  false for spectators and overlays: only public stats and non-private notes.
     * @return array<string, mixed>
     */
    public function present(Character $character, bool $isMember, bool $canUpdate): array
    {
        $character->loadMissing(self::RELATIONS);
        $note = $character->note;

        return [
            'id' => $character->id,
            'revision' => (int) $character->revision,
            'name' => $character->name,
            'class_key' => $character->characterClass?->key,
            'class_name' => $character->characterClass?->name,
            'portrait' => $this->icons->url($character->portrait),
            'can_update' => $canUpdate,
            'stats' => $character->stats
                ->filter(fn ($stat) => $isMember || $stat->definition->is_public)
                ->sortBy(fn ($stat) => [$stat->definition->display_order, $stat->definition->id])
                ->values()
                ->map(fn ($stat) => [
                    'id' => $stat->id,
                    'key' => $stat->definition->key,
                    'name' => $stat->definition->name,
                    'kind' => $stat->definition->kind ?? 'resource',
                    'icon' => $this->icon($stat->definition),
                    'value' => $stat->value,
                    'max_value' => $stat->max_value,
                    'minimum' => $stat->definition->minimum_value === null ? null : (float) $stat->definition->minimum_value,
                    'maximum' => $stat->definition->maximum_value === null ? null : (float) $stat->definition->maximum_value,
                ])->all(),
            'items' => $character->items->sortBy('id')->values()->map(fn (CharacterItem $item) => $this->item($character, $item))->all(),
            'spells' => $character->spells->sortBy('id')->values()->map(fn ($spell) => $this->ability($spell, $spell->definition))->all(),
            'skills' => $character->skills->sortBy('id')->values()->map(fn ($skill) => $this->ability($skill, $skill->definition))->all(),
            'note' => ! $note || ! $note->is_private || $canUpdate
                ? ['content' => $note?->content, 'is_private' => (bool) ($note?->is_private ?? false)]
                : null,
        ];
    }

    /**
     * Room-level context shared by every character in a room.
     *
     * @return array<string, mixed>
     */
    public function system(GameSession $game, bool $includeCatalog): array
    {
        $versionId = $game->game_system_version_id;
        $available = fn (string $model) => $model::query()->availableToUser($game->owner)->where('game_system_version_id', $versionId);
        $items = $includeCatalog ? $available(ItemDefinition::class)->orderBy('name')->get() : collect();
        $configured = collect($game->gameSystemVersion->configuration['item_categories'] ?? [])
            ->filter(fn ($category) => is_array($category) && isset($category['key']))
            ->map(fn ($category) => ['key' => (string) $category['key'], 'name' => (string) ($category['name'] ?? $category['key'])]);

        return [
            'rules_mode' => $this->rules->mode($game->gameSystemVersion),
            'layouts' => app(SheetLayouts::class)->resolve($game),
            'slots' => $available(SlotDefinition::class)->orderBy('display_order')->get()
                ->map(fn ($slot) => ['id' => $slot->id, 'key' => $slot->key, 'name' => $slot->name, 'icon' => $this->icon($slot), 'capacity' => $slot->capacity])->all(),
            'stats' => $available(StatDefinition::class)->orderBy('display_order')->get()
                ->map(fn ($stat) => ['key' => $stat->key, 'name' => $stat->name, 'kind' => $stat->kind ?? 'resource'])->all(),
            'item_categories' => $configured->isNotEmpty() ? $configured->values()->all() : $items
                ->map(fn ($item) => $this->rules->rules($item)['category'])->filter()->unique()->sort()->values()
                ->map(fn ($key) => ['key' => $key, 'name' => ucfirst($key)])->all(),
            'catalog' => $includeCatalog ? [
                'items' => $items->map(fn (ItemDefinition $item) => ['id' => $item->id, 'key' => $item->key, 'name' => $item->name, 'icon' => $this->icon($item), 'description' => $item->description] + $this->rules->rules($item))->all(),
                'spells' => $available(SpellDefinition::class)->orderBy('name')->get()->map(fn ($spell) => $this->definition($spell))->all(),
                'skills' => $available(SkillDefinition::class)->orderBy('name')->get()->map(fn ($skill) => $this->definition($skill))->all(),
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function item(Character $character, CharacterItem $item): array
    {
        $definition = $item->definition;

        return [
            'id' => $item->id,
            'definition_id' => $definition?->id,
            'key' => $definition?->key,
            'name' => $item->custom_name ?? $definition?->name ?? 'Item',
            'description' => $item->custom_description ?? $definition?->description,
            'icon' => $this->icon($definition),
            'quantity' => $item->quantity,
            'slots' => $item->slots->map(fn ($slot) => ['id' => $slot->definition->id, 'key' => $slot->definition->key, 'name' => $slot->definition->name])->values()->all(),
            'restriction' => $this->rules->restriction($character, $definition),
        ] + $this->rules->rules($definition);
    }

    /** @return array<string, mixed> */
    private function ability(Model $owned, ?Model $definition): array
    {
        return ['id' => $owned->getKey(), 'is_used' => (bool) $owned->getAttribute('is_used'), 'charges' => $owned->getAttribute('charges')] + $this->definition($definition);
    }

    /** @return array<string, mixed> */
    private function definition(?Model $definition): array
    {
        $config = $definition?->getAttribute('configuration') ?? [];

        return [
            'definition_id' => $definition?->getKey(),
            'key' => $definition?->getAttribute('key'),
            'name' => $definition?->getAttribute('name') ?? 'Unknown',
            'description' => $definition?->getAttribute('description'),
            'icon' => $this->icon($definition),
            'group' => is_string($config['group'] ?? null) ? $config['group'] : (is_string($config['category'] ?? null) ? $config['category'] : null),
        ];
    }

    private function icon(?Model $definition): ?string
    {
        $icon = $definition?->getAttribute('icon');

        return is_string($icon) ? $this->icons->url($icon) : null;
    }
}
