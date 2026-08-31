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
        $game->load([
            'gameSystemVersion.gameSystem',
            'gameSystemVersion.characterClasses',
            'members.user',
            'sessionCharacters.assignedUser',
            'sessionCharacters.character.characterClass',
            'sessionCharacters.character.stats.definition',
        ]);

        return Inertia::render('Games/Show', [
            'game' => [
                'id' => $game->id, 'name' => $game->name, 'join_code' => $game->join_code,
                'status' => $game->status, 'visibility' => $game->visibility,
                'system' => $game->gameSystemVersion->gameSystem->name,
                'can_manage_members' => $request->user()->can('manageMembers', $game),
                'classes' => $game->gameSystemVersion->characterClasses->sortBy('display_order')->values()->map(fn ($class) => [
                    'id' => $class->id, 'name' => $class->name,
                ]),
                'players' => $game->members->where('status', 'active')->whereIn('role', ['player', 'co_dm'])->map(fn ($member) => [
                    'id' => $member->user->id, 'name' => $member->user->name,
                ])->values(),
                'members' => $game->members->map(fn ($member) => [
                    'id' => $member->id, 'name' => $member->user->name, 'role' => $member->role, 'status' => $member->status,
                ]),
                'characters' => $game->sessionCharacters->where('is_active', true)->map(fn ($sessionCharacter) => [
                    'session_character_id' => $sessionCharacter->id,
                    'id' => $sessionCharacter->character->id,
                    'name' => $sessionCharacter->character->name,
                    'class_name' => $sessionCharacter->character->characterClass?->name,
                    'assigned_user_id' => $sessionCharacter->assigned_user_id,
                    'assigned_user_name' => $sessionCharacter->assignedUser?->name,
                    'can_update' => $request->user()->can('update', $sessionCharacter->character),
                    'stats' => $sessionCharacter->character->stats->sortBy('definition.display_order')->values()->map(fn ($stat) => [
                        'id' => $stat->id, 'key' => $stat->definition->key, 'name' => $stat->definition->name,
                        'value' => $stat->value, 'max_value' => $stat->max_value,
                    ]),
                ])->values(),
            ],
        ]);
    }
}
