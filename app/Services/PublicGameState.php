<?php

namespace App\Services;

use Illuminate\Support\Arr;

class PublicGameState
{
    /** Explicit allowlists keep future private fields out of spectator responses.
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function room(array $payload): array
    {
        $game = Arr::only($payload['game'], ['id', 'name', 'status', 'visibility', 'system', 'viewer_count', 'current_revision', 'current_character_id']);
        $game += ['join_code' => null, 'is_spectator' => true, 'can_manage_members' => false, 'can_manage_settings' => false, 'members' => [], 'players' => [], 'classes' => [], 'game_events' => [], 'turn_events' => []];
        $game['characters'] = collect($payload['game']['characters'])->map(fn (array $character) => $this->character($character))->values();

        return ['game' => $game];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sheet(array $payload): array
    {
        return [
            'game' => Arr::only($payload['game'], ['id', 'name', 'is_spectator', 'current_revision', 'current_character_id']),
            'character' => $this->character($payload['character']) + ['slots' => [], 'item_definitions' => [], 'spell_definitions' => [], 'skill_definitions' => []],
        ];
    }

    /** @param array<string, mixed> $character
     * @return array<string, mixed>
     */
    private function character(array $character): array
    {
        $public = Arr::only($character, ['id', 'session_character_id', 'name', 'class_name', 'gold', 'assigned_user_name', 'note']);
        $public += ['can_update' => false, 'assigned_user_id' => null];
        foreach ([
            'stats' => ['id', 'key', 'name', 'value', 'max_value'],
            'trays' => ['key', 'name', 'default_open'],
            'items' => ['id', 'name', 'description', 'icon', 'quantity', 'is_active', 'slots'],
            'spells' => ['id', 'name', 'description', 'icon', 'is_used', 'charges'],
            'skills' => ['id', 'name', 'description', 'icon', 'is_used', 'charges'],
        ] as $key => $fields) {
            $public[$key] = collect($character[$key])->map(fn (array $value) => Arr::only($value, $fields))->values();
        }

        return $public;
    }
}
