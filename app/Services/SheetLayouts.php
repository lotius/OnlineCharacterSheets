<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\TrayDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A layout is an ordered list of display blocks. The same block vocabulary renders the phone sheet,
 * the compact room cards, and hero cards inside stream overlays.
 *
 * Resolution: room settings.layouts.{name} → system version configuration.layouts.{name} → generated default.
 */
class SheetLayouts
{
    public const NAMES = ['sheet', 'card'];

    public const BLOCK_TYPES = ['stats', 'equipment', 'items', 'abilities', 'notes'];

    /** @return array{sheet: list<array<string, mixed>>, card: list<array<string, mixed>>} */
    public function resolve(GameSession $game): array
    {
        $version = $game->gameSystemVersion;
        $layouts = [];
        foreach (self::NAMES as $name) {
            $layouts[$name] = $this->normalize(
                $game->settings['layouts'][$name]
                    ?? $version->configuration['layouts'][$name]
                    ?? $this->generated($name, $game, $version),
            );
        }

        return $layouts;
    }

    /** @return list<array<string, mixed>> */
    public function systemDefault(string $name, GameSession $game): array
    {
        $version = $game->gameSystemVersion;

        return $this->normalize($version->configuration['layouts'][$name] ?? $this->generated($name, $game, $version));
    }

    /**
     * Validate a user-supplied layout (room override or system configuration).
     *
     * @return list<array<string, mixed>>
     */
    public function validate(mixed $blocks, string $attribute = 'layout'): array
    {
        $key = ['string', 'max:60', 'regex:/^[a-z0-9_-]+$/'];
        validator([$attribute => $blocks], [
            $attribute => ['array', 'max:30'],
            "{$attribute}.*" => ['array'],
            "{$attribute}.*.id" => ['nullable', 'string', 'max:40'],
            "{$attribute}.*.type" => ['required', Rule::in(self::BLOCK_TYPES)],
            "{$attribute}.*.title" => ['nullable', 'string', 'max:60'],
            "{$attribute}.*.collapsed" => ['nullable', 'boolean'],
            "{$attribute}.*.keys" => ['nullable', 'array', 'max:30'], "{$attribute}.*.keys.*" => $key,
            "{$attribute}.*.category" => ['nullable', 'string', 'max:60'],
            "{$attribute}.*.exclude_categories" => ['nullable', 'array', 'max:20'], "{$attribute}.*.exclude_categories.*" => ['string', 'max:60'],
            "{$attribute}.*.kind" => ['nullable', Rule::in(['spells', 'skills'])],
        ])->validate();

        return $this->normalize($blocks);
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @return list<array<string, mixed>>
     */
    public function normalize(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn ($block) => is_array($block) && in_array($block['type'] ?? null, self::BLOCK_TYPES, true))
            ->map(function (array $block) {
                $clean = [
                    'id' => is_string($block['id'] ?? null) && $block['id'] !== '' ? $block['id'] : Str::lower(Str::random(8)),
                    'type' => $block['type'],
                    'title' => is_string($block['title'] ?? null) && $block['title'] !== '' ? $block['title'] : null,
                    'collapsed' => (bool) ($block['collapsed'] ?? false),
                ];
                if ($block['type'] === 'stats' && ! empty($block['keys'])) {
                    $clean['keys'] = array_values($block['keys']);
                }
                if ($block['type'] === 'items') {
                    $clean['category'] = $block['category'] ?? null;
                    $clean['exclude_categories'] = array_values($block['exclude_categories'] ?? []);
                }
                if ($block['type'] === 'abilities') {
                    $clean['kind'] = ($block['kind'] ?? 'spells') === 'skills' ? 'skills' : 'spells';
                }

                return $clean;
            })->values()->all();
    }

    /** Build a default from the configured sheet sections ("trays") so existing systems keep working. */
    private function generated(string $name, GameSession $game, GameSystemVersion $version): array
    {
        /** @var Collection<int, TrayDefinition> $trays */
        $trays = TrayDefinition::query()->availableToUser($game->owner)
            ->where('game_system_version_id', $version->id)->orderBy('display_order')->get();
        $blocks = [['id' => 'stats', 'type' => 'stats']];
        $categories = $trays->pluck('key')->reject(fn ($key) => in_array($key, ['equipment', 'inventory', 'spells', 'skills', 'notes'], true))->values();

        foreach ($trays as $tray) {
            $block = match ($tray->key) {
                'equipment' => ['type' => 'equipment'],
                'inventory' => ['type' => 'items', 'exclude_categories' => $categories->all()],
                'spells', 'skills' => ['type' => 'abilities', 'kind' => $tray->key],
                'notes' => ['type' => 'notes'],
                default => ['type' => 'items', 'category' => $tray->key],
            };
            $blocks[] = $block + ['id' => $tray->key, 'title' => $tray->name, 'collapsed' => ! $tray->default_open];
        }

        if ($name === 'card') {
            return collect($blocks)->filter(fn ($block) => in_array($block['type'], ['stats', 'equipment'], true))
                ->push(['id' => 'items', 'type' => 'items', 'title' => 'Inventory', 'collapsed' => true])
                ->values()->all();
        }

        return $blocks;
    }
}
