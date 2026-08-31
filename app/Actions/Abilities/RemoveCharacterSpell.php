<?php

namespace App\Actions\Abilities;

use App\Models\Character;
use App\Models\CharacterSpell;
use Illuminate\Validation\ValidationException;

class RemoveCharacterSpell
{
    public function handle(Character $character, CharacterSpell $spell): void
    {
        if ($spell->character_id !== $character->id) {
            throw ValidationException::withMessages(['spell' => 'That spell does not belong to this character.']);
        }
        $spell->delete();
    }
}
