<?php

namespace App\Http\Controllers;

use App\Actions\Characters\AssignCharacter;
use App\Actions\Characters\UnassignCharacter;
use App\Actions\GameSessions\BroadcastGameSessionUpdate;
use App\Http\Requests\Characters\AssignCharacterRequest;
use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CharacterAssignmentController extends Controller
{
    public function update(AssignCharacterRequest $request, GameSession $game, SessionCharacter $sessionCharacter, AssignCharacter $assign, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $game);
        $player = User::query()->findOrFail($request->integer('assigned_user_id'));
        $assign->handle($game, $sessionCharacter, $player);
        $broadcast->room($game);

        return back()->with('success', 'Character assigned.');
    }

    public function destroy(GameSession $game, SessionCharacter $sessionCharacter, UnassignCharacter $unassign, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('update', $game);
        $unassign->handle($game, $sessionCharacter);
        $broadcast->room($game);

        return back()->with('success', 'Character unassigned.');
    }
}
