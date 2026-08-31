<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\CreateGameSession;
use App\Http\Requests\GameSessions\StoreGameSessionRequest;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GameSessionController extends Controller
{
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
                'name' => $version->gameSystem->name.' — '.($version->name ?? $version->version),
            ]),
        ]);
    }

    public function store(StoreGameSessionRequest $request, CreateGameSession $create): RedirectResponse
    {
        $version = GameSystemVersion::query()->findOrFail($request->integer('game_system_version_id'));
        $session = $create->handle($request->user(), $version, $request->validated());

        return redirect()->route('games.show', $session)->with('success', 'Room created.');
    }

    public function show(Request $request, GameSession $game): Response
    {
        $this->authorize('view', $game);
        $game->load(['gameSystemVersion.gameSystem', 'members.user']);

        return Inertia::render('Games/Show', [
            'game' => [
                'id' => $game->id, 'name' => $game->name, 'join_code' => $game->join_code,
                'status' => $game->status, 'visibility' => $game->visibility,
                'system' => $game->gameSystemVersion->gameSystem->name,
                'can_manage_members' => $request->user()->can('manageMembers', $game),
                'members' => $game->members->map(fn ($member) => [
                    'id' => $member->id, 'name' => $member->user->name, 'role' => $member->role, 'status' => $member->status,
                ]),
            ],
        ]);
    }
}
