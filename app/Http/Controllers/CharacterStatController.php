<?php

namespace App\Http\Controllers;

use App\Actions\Characters\UpdateCharacterStat;
use App\Http\Requests\Characters\UpdateCharacterStatRequest;
use App\Models\Character;
use App\Models\CharacterStat;
use Illuminate\Http\RedirectResponse;

class CharacterStatController extends Controller
{
    public function update(UpdateCharacterStatRequest $request, Character $character, CharacterStat $stat, UpdateCharacterStat $update): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $stat, $request->integer('value'));

        return back();
    }
}
