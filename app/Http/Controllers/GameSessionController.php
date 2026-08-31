<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\CreateGameSession;
use App\Http\Requests\GameSessions\StoreGameSessionRequest;
use App\Models\CharacterClassDefinition;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\TrayDefinition;
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
        $user = $request->user();
        $isMember = $user?->can('view', $game) ?? false;
        abort_unless($game->visibility === 'public_view' || $isMember, 403);
        $game->load([
            'gameSystemVersion.gameSystem',
            'gameSystemVersion.characterClasses',
            'members.user',
            'sessionCharacters.assignedUser',
            'sessionCharacters.character.characterClass',
            'sessionCharacters.character.stats.definition',
            'sessionCharacters.character.items.definition',
            'sessionCharacters.character.items.slots.definition',
            'sessionCharacters.character.spells.definition',
            'sessionCharacters.character.skills.definition',
            'sessionCharacters.character.note',
            'gameSystemVersion.trays',
        ]);

        $canManageMembers = $user?->can('manageMembers', $game) ?? false;
        $classes = CharacterClassDefinition::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('display_order')->get();
        $trays = TrayDefinition::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('display_order')->get();

        return Inertia::render('Games/Show', [
            'game' => [
                'id' => $game->id, 'name' => $game->name, 'join_code' => $game->join_code,
                'status' => $game->status, 'visibility' => $game->visibility,
                'system' => $game->gameSystemVersion->gameSystem->name,
                'can_manage_members' => $canManageMembers,
                'is_spectator' => ! $isMember,
                'classes' => $classes->map(fn ($class) => [
                    'id' => $class->id, 'name' => $class->name,
                ]),
                'players' => $game->members->where('status', 'active')->whereIn('role', ['player', 'co_dm'])->map(fn ($member) => [
                    'id' => $member->user->id, 'name' => $member->user->name,
                ])->values(),
                'members' => $isMember ? $game->members->map(fn ($member) => [
                    'id' => $member->id, 'name' => $member->user->name, 'role' => $member->role, 'status' => $member->status,
                ]) : [],
                'characters' => $game->sessionCharacters->where('is_active', true)->map(function ($sessionCharacter) use ($user, $trays) {
                    $character = $sessionCharacter->character;
                    $canUpdate = $user?->can('update', $character) ?? false;

                    return [
                        'session_character_id' => $sessionCharacter->id,
                        'id' => $character->id,
                        'name' => $character->name,
                        'class_name' => $character->characterClass?->name,
                        'assigned_user_id' => $sessionCharacter->assigned_user_id,
                        'assigned_user_name' => $sessionCharacter->assignedUser?->name,
                        'can_update' => $canUpdate,
                        'stats' => $character->stats->sortBy('definition.display_order')->values()->map(fn ($stat) => [
                            'id' => $stat->id, 'key' => $stat->definition->key, 'name' => $stat->definition->name,
                            'value' => $stat->value, 'max_value' => $stat->max_value,
                        ]),
                        'trays' => $trays->map(fn ($tray) => [
                            'key' => $tray->key, 'name' => $tray->name, 'default_open' => $tray->default_open,
                        ]),
                        'items' => $character->items->map(fn ($item) => [
                            'id' => $item->id, 'name' => $item->custom_name ?? $item->definition?->name,
                            'description' => $item->custom_description ?? $item->definition?->description,
                            'quantity' => $item->quantity,
                            'slots' => $item->slots->map(fn ($slot) => $slot->definition->name)->values(),
                        ]),
                        'spells' => $character->spells->map(fn ($spell) => [
                            'id' => $spell->id, 'name' => $spell->definition->name,
                            'description' => $spell->definition->description, 'is_used' => $spell->is_used,
                        ]),
                        'skills' => $character->skills->map(fn ($skill) => [
                            'id' => $skill->id, 'name' => $skill->definition->name,
                            'description' => $skill->definition->description, 'is_used' => $skill->is_used,
                        ]),
                        'note' => ! $character->note || (! $character->note->is_private || $canUpdate)
                            ? $character->note?->content
                            : null,
                    ];
                })->values(),
            ],
        ]);
    }
}
