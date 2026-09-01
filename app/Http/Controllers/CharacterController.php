<?php

namespace App\Http\Controllers;

use App\Actions\Characters\CreateCharacter;
use App\Actions\Characters\RemoveCharacterFromGameSession;
use App\Actions\GameSessions\BroadcastGameSessionUpdate;
use App\Http\Requests\Characters\StoreCharacterRequest;
use App\Models\GameSession;
use App\Models\SessionCharacter;
use Illuminate\Http\RedirectResponse;

class CharacterController extends Controller
{
    public function store(StoreCharacterRequest $request, GameSession $game, CreateCharacter $create, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $game);
        $create->handle($request->user(), $game, $request->validated());
        $broadcast->room($game);

        return back()->with('success', 'Character created.');
    }

    public function destroy(GameSession $game, SessionCharacter $sessionCharacter, RemoveCharacterFromGameSession $remove, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $game);
        $remove->handle($game, $sessionCharacter);
        $broadcast->room($game);

        return back()->with('success', 'Character removed.');
    }
}
