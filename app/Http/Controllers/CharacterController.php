<?php

namespace App\Http\Controllers;

use App\Actions\Characters\CreateCharacter;
use App\Http\Requests\Characters\StoreCharacterRequest;
use App\Models\GameSession;
use Illuminate\Http\RedirectResponse;

class CharacterController extends Controller
{
    public function store(StoreCharacterRequest $request, GameSession $game, CreateCharacter $create): RedirectResponse
    {
        $this->authorize('update', $game);
        $create->handle($request->user(), $game, $request->validated());

        return back()->with('success', 'Character created.');
    }
}
