<?php

namespace App\Services;

use Illuminate\Support\Arr;

class PublicGameState
{
    private const CHARACTER = ['id', 'revision', 'session_character_id', 'name', 'class_key', 'class_name', 'portrait', 'assigned_user_name', 'note'];

    private const COLLECTIONS = [
        'stats' => ['id', 'key', 'name', 'kind', 'icon', 'value', 'max_value', 'minimum', 'maximum'],
        'items' => ['id', 'definition_id', 'key', 'name', 'description', 'icon', 'quantity', 'slots', 'category', 'cost', 'modifiers', 'allowed_slots', 'required_slots', 'consumable', 'tags', 'properties'],
        'spells' => ['id', 'definition_id', 'key', 'name', 'description', 'icon', 'group', 'is_used', 'charges'],
        'skills' => ['id', 'definition_id', 'key', 'name', 'description', 'icon', 'group', 'is_used', 'charges'],
    ];

    /** Explicit allowlists keep future private fields out of spectator and overlay responses.
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function room(array $payload): array
    {
        $game = Arr::only($payload['game'], ['id', 'name', 'status', 'visibility', 'system', 'viewer_count', 'current_revision', 'current_character_id']);
        $game += ['join_code' => null, 'is_spectator' => true, 'can_manage_members' => false, 'can_manage_settings' => false, 'my_user_id' => null, 'members' => [], 'players' => [], 'classes' => [], 'overlays' => [], 'game_events' => [], 'turn_events' => [], 'sheet_layouts' => ['room' => null, 'system' => null]];
        $game['characters'] = collect($payload['game']['characters'])->map(fn (array $character) => $this->character($character))->values();

        return ['game' => $game, 'system' => $this->system($payload['system'])];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sheet(array $payload): array
    {
        $sheet = [
            'game' => Arr::only($payload['game'], ['id', 'name', 'is_spectator', 'current_revision', 'current_character_id']),
            'character' => $this->character($payload['character']),
        ];
        if (isset($payload['system'])) {
            $sheet['system'] = $this->system($payload['system']);
        }

        return $sheet;
    }

    /** @param array<string, mixed> $character
     * @return array<string, mixed>
     */
    public function character(array $character): array
    {
        $public = Arr::only($character, self::CHARACTER);
        $public += ['can_update' => false, 'assigned_user_id' => null];
        foreach (self::COLLECTIONS as $key => $fields) {
            $public[$key] = collect($character[$key] ?? [])->map(fn (array $value) => Arr::only($value, $fields))->values()->all();
        }

        return $public;
    }

    /** @param array<string, mixed> $system
     * @return array<string, mixed>
     */
    private function system(array $system): array
    {
        return Arr::only($system, ['rules_mode', 'layouts', 'slots', 'stats', 'item_categories']) + ['catalog' => null];
    }
}
