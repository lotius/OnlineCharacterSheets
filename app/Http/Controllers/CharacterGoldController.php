<?php

namespace App\Http\Controllers;

use App\Actions\Characters\UpdateCharacterGold;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterGoldController extends Controller
{
    public function update(Request $request, Character $character, UpdateCharacterGold $update): RedirectResponse
    {
        $this->authorize('update', $character);

        $validated = $request->validate([
            'gold' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $update->handle($character, (int) $validated['gold']);

        return back();
    }
}
