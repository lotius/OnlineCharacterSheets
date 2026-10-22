<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterNote;
use App\Services\CharacterMutation;

class UpdateCharacterNote
{
    public function handle(Character $character, ?string $content, bool $isPrivate): CharacterNote
    {
        return app(CharacterMutation::class)->run($character, 'note.changed', 'note', $character->id, fn () => $character->note()->updateOrCreate([], ['content' => $content, 'is_private' => $isPrivate]));
    }
}
