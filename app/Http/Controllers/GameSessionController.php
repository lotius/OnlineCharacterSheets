<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\CreateGameSession;
use App\Actions\GameSessions\UpdateGameSession;
use App\Http\Requests\GameSessions\StoreGameSessionRequest;
use App\Http\Requests\GameSessions\UpdateGameSessionRequest;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Services\RoomState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameSessionController extends Controller
{
    private const VIEWER_STALE_SECONDS = 45;

    public function index(Request $request): Response
    {
        $memberships = $request->user()->gameSessionMemberships()->with('gameSession.gameSystemVersion.gameSystem')->latest()->get();

        return Inertia::render('Dashboard', [
            'rooms' => $memberships->map(fn ($membership) => [
                'id' => $membership->gameSession->id,
                'name' => $membership->gameSession->name,
                'system' => $membership->gameSession->gameSystemVersion->gameSystem->name,
                'role' => $membership->role,
                'membership_status' => $membership->status,
                'room_status' => $membership->gameSession->status,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Games/Create', [
            'versions' => GameSystemVersion::query()->where('is_current', true)->whereHas('gameSystem', fn ($query) => $query->where('is_active', true))->with('gameSystem')->get()->map(fn ($version) => [
                'id' => $version->id,
                'name' => $version->display_name,
            ]),
        ]);
    }

    public function store(StoreGameSessionRequest $request, CreateGameSession $create): RedirectResponse
    {
        $version = GameSystemVersion::query()->findOrFail($request->integer('game_system_version_id'));
        $session = $create->handle($request->user(), $version, $request->validated());

        return redirect()->route('games.show', $session)->with('success', 'Room created.');
    }

    public function update(UpdateGameSessionRequest $request, GameSession $game, UpdateGameSession $update): RedirectResponse
    {
        $update->handle($game, $request->validated());

        return back()->with('success', 'Room settings saved.');
    }

    public function show(Request $request, GameSession $game, RoomState $state): Response
    {
        return Inertia::render('Games/Show', $state->forViewer($request->user(), $game));
    }
}
