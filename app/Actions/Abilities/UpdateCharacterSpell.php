<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSpell;
use Illuminate\Validation\ValidationException;

class UpdateCharacterSpell
{
    /** @param array{is_used?: bool, charges?: int|null} $attributes */
    public function handle(Character $character, CharacterSpell $spell, array $attributes): CharacterSpell
    {
        if ($spell->character_id !== $character->id) {
            throw ValidationException::withMessages(['spell' => 'That spell does not belong to this character.']);
        }
        $spell->update($attributes);

        return $spell->refresh();
    }
}
