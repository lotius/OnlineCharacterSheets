<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSkill;
use Illuminate\Validation\ValidationException;

class UpdateCharacterSkill
{
    /** @param array{is_used?: bool, charges?: int|null} $attributes */
    public function handle(Character $character, CharacterSkill $skill, array $attributes): CharacterSkill
    {
        if ($skill->character_id !== $character->id) {
            throw ValidationException::withMessages(['skill' => 'That skill does not belong to this character.']);
        }
        $skill->update($attributes);

        return $skill->refresh();
    }
}
