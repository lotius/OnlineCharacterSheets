<?php

namespace App\Http\Controllers;

use App\Actions\Characters\UpdateCharacterStat;
use App\Http\Requests\Characters\UpdateCharacterStatRequest;
use App\Models\Character;
use App\Models\CharacterStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CharacterStatController extends Controller
{
    public function update(UpdateCharacterStatRequest $request, Character $character, CharacterStat $stat, UpdateCharacterStat $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $stat, [
            'value' => $request->filled('value') ? $request->integer('value') : null,
            'delta' => $request->filled('delta') ? $request->integer('delta') : null,
            'max_value' => $request->filled('max_value') ? $request->integer('max_value') : null,
        ]);

        return $this->characterResponse($request, $character);
    }
}
