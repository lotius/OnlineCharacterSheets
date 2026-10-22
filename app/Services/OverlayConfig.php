<?php

namespace App\Services;

use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Models\StreamOverlay;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Overlay layouts are versioned JSON: absolutely positioned elements on a fixed logical canvas, bound to
 * "hero slots" (1..n in turn order unless mapped), the current-turn hero, or nothing. Only named element
 * types, whitelisted props/styles and safe presets are accepted — never HTML, CSS or script.
 */
class OverlayConfig
{
    public const ELEMENT_TYPES = ['text', 'name', 'class', 'player', 'portrait', 'stat', 'item-count', 'equipped', 'turn-marker', 'box', 'image', 'hero-card'];

    public const PRESETS = ['hero-column' => 'Hero column (left edge)', 'hero-row' => 'Hero cards (bottom row)', 'current-hero' => 'Current hero card', 'blank' => 'Blank canvas'];

    private const COLOR = 'regex:/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)|transparent)$/';

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function validate(array $config): array
    {
        $key = ['string', 'max:60', 'regex:/^[a-z0-9_-]+$/'];
        validator($config, [
            'version' => ['required', Rule::in([1])],
            'width' => ['required', 'integer', 'between:320,7680'],
            'height' => ['required', 'integer', 'between:240,4320'],
            'hero_slots' => ['nullable', 'array', 'max:12'],
            'hero_slots.*' => ['nullable', 'integer'],
            'elements' => ['present', 'array', 'max:200'],
            'elements.*.id' => ['required', 'string', 'max:40'],
            'elements.*.type' => ['required', Rule::in(self::ELEMENT_TYPES)],
            'elements.*.x' => ['required', 'numeric', 'between:-8000,16000'],
            'elements.*.y' => ['required', 'numeric', 'between:-8000,16000'],
            'elements.*.w' => ['required', 'numeric', 'between:1,16000'],
            'elements.*.h' => ['required', 'numeric', 'between:1,16000'],
            'elements.*.z' => ['nullable', 'integer', 'between:-1000,1000'],
            'elements.*.hero' => ['nullable'],
            'elements.*.show' => ['nullable', Rule::in(['always', 'occupied', 'empty', 'current', 'not-current'])],
            'elements.*.animate' => ['nullable', Rule::in(['none', 'pulse', 'flash', 'bump', 'shake'])],
            'elements.*.props' => ['nullable', 'array'],
            'elements.*.props.text' => ['nullable', 'string', 'max:300'],
            'elements.*.props.stat' => ['nullable', ...$key],
            'elements.*.props.display' => ['nullable', Rule::in(['value', 'value-max', 'bar', 'pips', 'icon-value'])],
            'elements.*.props.item' => ['nullable', ...$key],
            'elements.*.props.category' => ['nullable', 'string', 'max:60'],
            'elements.*.props.src' => ['nullable', 'string', 'max:500', 'regex:#^(https://|/)[^\s"\'<>]*$#'],
            'elements.*.props.label' => ['nullable', 'string', 'max:60'],
            'elements.*.props.blocks' => ['nullable', 'array', 'max:10'],
            'elements.*.style' => ['nullable', 'array'],
            'elements.*.style.fontSize' => ['nullable', 'numeric', 'between:4,600'],
            'elements.*.style.fontWeight' => ['nullable', 'integer', 'between:100,900'],
            'elements.*.style.fontFamily' => ['nullable', Rule::in(['sans', 'serif', 'display', 'mono'])],
            'elements.*.style.color' => ['nullable', 'string', self::COLOR],
            'elements.*.style.background' => ['nullable', 'string', self::COLOR],
            'elements.*.style.borderColor' => ['nullable', 'string', self::COLOR],
            'elements.*.style.borderWidth' => ['nullable', 'numeric', 'between:0,100'],
            'elements.*.style.radius' => ['nullable', 'numeric', 'between:0,5000'],
            'elements.*.style.padding' => ['nullable', 'numeric', 'between:0,500'],
            'elements.*.style.opacity' => ['nullable', 'numeric', 'between:0,1'],
            'elements.*.style.align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            'elements.*.style.shadow' => ['nullable', 'boolean'],
            'elements.*.style.glow' => ['nullable', 'string', self::COLOR],
        ])->validate();

        $layouts = app(SheetLayouts::class);
        $allowedProps = ['text', 'stat', 'display', 'item', 'category', 'src', 'label', 'blocks'];
        $allowedStyle = ['fontSize', 'fontWeight', 'fontFamily', 'color', 'background', 'borderColor', 'borderWidth', 'radius', 'padding', 'opacity', 'align', 'shadow', 'glow'];

        $config['elements'] = collect($config['elements'])->map(function (array $element, int $index) use ($layouts, $allowedProps, $allowedStyle) {
            $hero = $element['hero'] ?? null;
            if (! ($hero === null || $hero === 'current' || (is_int($hero) && $hero >= 1 && $hero <= 12))) {
                throw ValidationException::withMessages(["elements.{$index}.hero" => 'Bind to hero 1–12, the current hero, or nothing.']);
            }
            $props = Arr::only($element['props'] ?? [], $allowedProps);
            if (isset($props['blocks'])) {
                $props['blocks'] = $layouts->validate($props['blocks'], "elements.{$index}.props.blocks");
            }

            return [
                'id' => $element['id'], 'type' => $element['type'],
                'x' => (float) $element['x'], 'y' => (float) $element['y'], 'w' => (float) $element['w'], 'h' => (float) $element['h'],
                'z' => (int) ($element['z'] ?? 0), 'hero' => $hero,
                'show' => $element['show'] ?? 'always', 'animate' => $element['animate'] ?? 'none',
                'props' => $props, 'style' => Arr::only($element['style'] ?? [], $allowedStyle),
            ];
        })->values()->all();

        return Arr::only($config, ['version', 'width', 'height', 'hero_slots', 'elements']) + ['hero_slots' => null];
    }

    /** @return array<string, mixed> */
    public function preset(string $preset): array
    {
        $base = ['version' => 1, 'width' => 1920, 'height' => 1080, 'hero_slots' => null, 'elements' => []];
        $id = fn () => Str::lower(Str::random(8));

        if ($preset === 'hero-column') {
            // Vertical hero rows: portrait, big Body value, potion count, open-spot placeholder, turn glow.
            foreach (range(1, 5) as $hero) {
                $y = 120 + ($hero - 1) * 160;
                $row = ['x' => 24, 'y' => $y, 'w' => 340, 'h' => 136, 'hero' => $hero];
                array_push($base['elements'],
                    ['id' => $id(), 'type' => 'box', ...$row, 'show' => 'occupied', 'style' => ['background' => 'rgba(12,9,7,0.62)', 'radius' => 68]],
                    ['id' => $id(), 'type' => 'turn-marker', ...$row, 'show' => 'current', 'animate' => 'pulse', 'style' => ['borderColor' => '#facc15', 'borderWidth' => 4, 'radius' => 68, 'glow' => 'rgba(250,204,21,0.55)']],
                    ['id' => $id(), 'type' => 'portrait', 'x' => 34, 'y' => $y + 10, 'w' => 116, 'h' => 116, 'hero' => $hero, 'show' => 'occupied', 'style' => ['radius' => 999, 'borderColor' => '#d9a441', 'borderWidth' => 3]],
                    ['id' => $id(), 'type' => 'stat', 'x' => 160, 'y' => $y + 8, 'w' => 96, 'h' => 92, 'hero' => $hero, 'show' => 'occupied', 'animate' => 'bump', 'props' => ['stat' => 'body', 'display' => 'value'], 'style' => ['fontSize' => 76, 'fontWeight' => 800, 'color' => '#facc15', 'align' => 'center', 'shadow' => true, 'fontFamily' => 'display']],
                    ['id' => $id(), 'type' => 'item-count', 'x' => 256, 'y' => $y + 26, 'w' => 100, 'h' => 60, 'hero' => $hero, 'show' => 'occupied', 'animate' => 'bump', 'props' => ['category' => 'potion', 'display' => 'icon-value'], 'style' => ['fontSize' => 40, 'fontWeight' => 800, 'color' => '#4ade80', 'shadow' => true]],
                    ['id' => $id(), 'type' => 'name', 'x' => 160, 'y' => $y + 98, 'w' => 196, 'h' => 30, 'hero' => $hero, 'show' => 'occupied', 'style' => ['fontSize' => 20, 'fontWeight' => 700, 'color' => '#f5ead2', 'shadow' => true]],
                    ['id' => $id(), 'type' => 'text', ...$row, 'show' => 'empty', 'props' => ['text' => 'Hero spot open'], 'style' => ['fontSize' => 28, 'fontWeight' => 700, 'color' => '#d6d3d1', 'background' => 'rgba(12,9,7,0.35)', 'borderColor' => 'rgba(217,164,65,0.5)', 'borderWidth' => 2, 'radius' => 68, 'align' => 'center', 'fontFamily' => 'display']],
                );
            }
        } elseif ($preset === 'hero-row') {
            foreach (range(1, 5) as $hero) {
                $x = 40 + ($hero - 1) * 372;
                array_push($base['elements'],
                    ['id' => $id(), 'type' => 'turn-marker', 'x' => $x - 6, 'y' => 794, 'w' => 352, 'h' => 272, 'hero' => $hero, 'show' => 'current', 'animate' => 'pulse', 'style' => ['borderColor' => '#facc15', 'borderWidth' => 4, 'radius' => 14, 'glow' => 'rgba(250,204,21,0.5)']],
                    ['id' => $id(), 'type' => 'hero-card', 'x' => $x, 'y' => 800, 'w' => 340, 'h' => 260, 'hero' => $hero, 'show' => 'occupied', 'animate' => 'flash', 'props' => ['blocks' => [['type' => 'stats'], ['type' => 'equipment']]], 'style' => ['background' => 'rgba(12,9,7,0.78)', 'radius' => 10, 'padding' => 12, 'fontSize' => 16]],
                );
            }
        } elseif ($preset === 'current-hero') {
            $base['elements'][] = ['id' => $id(), 'type' => 'hero-card', 'x' => 1480, 'y' => 560, 'w' => 400, 'h' => 480, 'hero' => 'current', 'show' => 'occupied', 'animate' => 'flash', 'props' => ['blocks' => [['type' => 'stats'], ['type' => 'equipment'], ['type' => 'items', 'category' => 'potion', 'title' => 'Potions']]], 'style' => ['background' => 'rgba(12,9,7,0.8)', 'radius' => 12, 'padding' => 14, 'fontSize' => 18]];
        }

        return $this->validate($base);
    }

    /**
     * Read-only state for a browser source: public character fields only.
     *
     * @return array<string, mixed>
     */
    public function state(StreamOverlay $overlay): array
    {
        $game = GameSession::query()->with(['gameSystemVersion', 'owner', 'sessionCharacters.assignedUser',
            ...array_map(fn ($relation) => "sessionCharacters.character.{$relation}", CharacterPresenter::RELATIONS)])
            ->findOrFail($overlay->game_session_id);
        $presenter = app(CharacterPresenter::class);
        $public = app(PublicGameState::class);
        $system = $presenter->system($game, false);

        return [
            'overlay' => ['id' => $overlay->id, 'name' => $overlay->name, 'configuration' => $overlay->configuration, 'channel' => "overlays.{$overlay->channel_key}"],
            'game' => ['id' => $game->id, 'name' => $game->name, 'current_character_id' => $game->current_character_id, 'current_revision' => $game->current_revision],
            'characters' => app(RoomState::class)->activeCharacters($game)->map(fn (SessionCharacter $entry) => $public->character([
                'session_character_id' => $entry->id, 'assigned_user_name' => $entry->assignedUser?->name,
            ] + $presenter->present($entry->character, false, false)))->values()->all(),
            'system' => Arr::only($system, ['slots', 'stats', 'item_categories', 'layouts']),
        ];
    }
}
