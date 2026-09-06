<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\BroadcastGameSessionUpdate;
use App\Models\Character;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterGoldController extends Controller
{
    public function update(Request $request, Character $character, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $character);

        $validated = $request->validate([
            'gold' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $character->update(['gold' => $validated['gold']]);
        $broadcast->character($character);

        return back();
    }
}
