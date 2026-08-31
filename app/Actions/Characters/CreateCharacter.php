<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterClassDefinition;
use App\Models\GameSession;
use App\Models\StatDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCharacter
{
    /** @param array{name: string, character_class_definition_id?: int|null} $attributes */
    public function handle(User $actor, GameSession $session, array $attributes): Character
    {
        return DB::transaction(function () use ($actor, $session, $attributes): Character {
            $class = null;

            if (! empty($attributes['character_class_definition_id'])) {
                $class = CharacterClassDefinition::query()->findOrFail($attributes['character_class_definition_id']);

                if ($class->game_system_version_id !== $session->game_system_version_id || ($class->owner_user_id && $class->owner_user_id !== $session->owner_user_id)) {
                    throw ValidationException::withMessages(['character_class_definition_id' => 'That class does not belong to this room’s game system.']);
                }
            }

            $character = Character::query()->create([
                'game_system_version_id' => $session->game_system_version_id,
                'character_class_definition_id' => $class?->id,
                'owner_user_id' => $actor->id,
                'name' => $attributes['name'],
            ]);

            $session->sessionCharacters()->create(['character_id' => $character->id]);
            $classStats = $class?->configuration['default_stats'] ?? [];

            foreach (StatDefinition::query()->availableToUser($session->owner)->where('game_system_version_id', $session->game_system_version_id)->orderBy('display_order')->get() as $definition) {
                $value = $classStats[$definition->key] ?? $definition->default_value;
                $character->stats()->create([
                    'stat_definition_id' => $definition->id,
                    'value' => $value,
                    'max_value' => $classStats[$definition->key] ?? $definition->default_max_value,
                ]);
            }

            return $character->load(['characterClass', 'stats.definition']);
        });
    }
}
