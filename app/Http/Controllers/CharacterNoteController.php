<?php

namespace App\Http\Controllers;

use App\Actions\Characters\UpdateCharacterNote;
use App\Http\Requests\Characters\UpdateCharacterNoteRequest;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;

class CharacterNoteController extends Controller
{
    public function update(UpdateCharacterNoteRequest $request, Character $character, UpdateCharacterNote $update): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $request->string('content')->toString() ?: null, $request->boolean('is_private'));

        return back()->with('success', 'Notes saved.');
    }
}
