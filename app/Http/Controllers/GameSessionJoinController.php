<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\RequestGameSessionJoin;
use App\Http\Requests\GameSessions\JoinGameSessionRequest;
use App\Models\GameSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class GameSessionJoinController extends Controller
{
    public function store(JoinGameSessionRequest $request, RequestGameSessionJoin $join): RedirectResponse
    {
        $game = GameSession::query()->where('join_code', Str::upper((string) $request->string('join_code')))->first();

        if (! $game) {
            return back()->withErrors(['join_code' => 'No room was found for that join code.']);
        }

        if ($game->owner_user_id === $request->user()->id) {
            return redirect()->route('games.show', $game);
        }

        $membership = $join->handle($request->user(), $game);

        return redirect()->route('games.index')->with('success', $membership->status === 'active' ? 'Room joined.' : 'Join request sent.');
    }
}
