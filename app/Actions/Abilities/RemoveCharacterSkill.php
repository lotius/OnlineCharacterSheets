<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSkill;
use Illuminate\Validation\ValidationException;

class RemoveCharacterSkill
{
    public function handle(Character $character, CharacterSkill $skill): void
    {
        if ($skill->character_id !== $character->id) {
            throw ValidationException::withMessages(['skill' => 'That skill does not belong to this character.']);
        }
        $skill->delete();
    }
}
