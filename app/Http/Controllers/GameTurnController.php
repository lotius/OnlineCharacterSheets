<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\ChangeTurn;
use App\Actions\GameSessions\UpdateTurnOrder;
use App\Http\Requests\GameSessions\ChangeTurnRequest;
use App\Http\Requests\GameSessions\UpdateTurnOrderRequest;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Services\CharacterMutation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameTurnController extends Controller
{
    public function order(UpdateTurnOrderRequest $request, GameSession $game, UpdateTurnOrder $update): RedirectResponse
    {
        $update->handle($game, array_map('intval', $request->validated('character_ids')));

        return back();
    }

    public function update(ChangeTurnRequest $request, GameSession $game, ChangeTurn $change): RedirectResponse
    {
        $change->handle($game, $request->user(), $request->validated('direction'), $request->filled('character_id') ? $request->integer('character_id') : null);

        return back();
    }

    public function undo(Request $request, GameSession $game, GameEvent $event, ChangeTurn $change, CharacterMutation $mutations): RedirectResponse
    {
        if ($event->character_id !== null) {
            $mutations->undo($game, $event, $request->user());
        } else {
            $change->handle($game, $request->user(), 'select', undoEventId: $event->id);
        }

        return back();
    }
}
