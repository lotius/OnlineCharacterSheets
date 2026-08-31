<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterNote;

class UpdateCharacterNote
{
    public function handle(Character $character, ?string $content, bool $isPrivate): CharacterNote
    {
        return $character->note()->updateOrCreate([], ['content' => $content, 'is_private' => $isPrivate]);
    }
}
