<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSpell;
use App\Models\SpellDefinition;
use Illuminate\Validation\ValidationException;

class AddCharacterSpell
{
    public function handle(Character $character, SpellDefinition $definition): CharacterSpell
    {
        if ($definition->game_system_version_id !== $character->game_system_version_id) {
            throw ValidationException::withMessages(['spell_definition_id' => 'That spell is not valid for this character.']);
        }

        return CharacterSpell::query()->firstOrCreate(['character_id' => $character->id, 'spell_definition_id' => $definition->id]);
    }
}
