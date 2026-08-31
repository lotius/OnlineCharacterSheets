<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\SkillDefinition;
use Illuminate\Validation\ValidationException;

class AddCharacterSkill
{
    public function handle(Character $character, SkillDefinition $definition): CharacterSkill
    {
        $ownerAllowed = ! $definition->owner_user_id || $character->sessions()
            ->whereHas('gameSession', fn ($query) => $query->where('owner_user_id', $definition->owner_user_id))->exists();

        if ($definition->game_system_version_id !== $character->game_system_version_id || ! $ownerAllowed) {
            throw ValidationException::withMessages(['skill_definition_id' => 'That skill is not valid for this character.']);
        }

        return CharacterSkill::query()->firstOrCreate(['character_id' => $character->id, 'skill_definition_id' => $definition->id]);
    }
}
