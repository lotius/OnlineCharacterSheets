<?php

namespace App\Services;

use App\Models\CharacterClassDefinition;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\SessionCharacter;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomState
{
    private const VIEWER_STALE_SECONDS = 45;

    public function __construct(private CharacterPresenter $presenter) {}

    /** @return array<string, mixed> */
    public function forViewer(?User $user, GameSession $game): array
    {
        // A plain transaction gives a consistent snapshot (InnoDB repeatable read) without blocking writers.
        return DB::transaction(function () use ($user, $game) {
            $game = GameSession::query()->findOrFail($game->id);
            $isMember = $user?->can('view', $game) ?? false;
            abort_unless($game->visibility === 'public_view' || $isMember, 403);
            $game->load([
                'gameSystemVersion.gameSystem', 'owner', 'members.user', 'sessionCharacters.assignedUser',
                ...array_map(fn ($relation) => "sessionCharacters.character.{$relation}", CharacterPresenter::RELATIONS),
            ]);

            $canManage = $user?->can('manageMembers', $game) ?? false;
            $characters = $this->activeCharacters($game);
            $canEditAny = $isMember && $characters->contains(fn ($entry) => $user?->can('update', $entry->character));
            $history = app(GameEventHistory::class);

            $payload = [
                'game' => [
                    'id' => $game->id, 'name' => $game->name, 'join_code' => $isMember ? $game->join_code : null,
                    'status' => $game->status, 'visibility' => $game->visibility,
                    'current_character_id' => $game->current_character_id,
                    'current_revision' => $game->current_revision,
                    'game_events' => $canManage ? GameEvent::query()->where('game_session_id', $game->id)->latest('revision')->limit(20)->get()->map(fn ($event) => $history->entry($event)) : [],
                    'turn_events' => $canManage ? GameEvent::query()->where('game_session_id', $game->id)->whereNull('character_id')->withExists('compensation')->latest('revision')->limit(20)->get()->map(fn ($event) => [
                        'id' => $event->id, 'revision' => $event->revision, 'event_type' => $event->event_type,
                        'before_character_id' => $event->before_state['current_character_id'] ?? null,
                        'after_character_id' => $event->after_state['current_character_id'] ?? null,
                        'undone' => $event->compensation_exists,
                    ]) : [],
                    'viewer_count' => $game->viewers()->where('last_seen_at', '>=', now()->subSeconds(self::VIEWER_STALE_SECONDS))->count(),
                    'system' => $game->gameSystemVersion->gameSystem->name,
                    'can_manage_members' => $canManage,
                    'can_manage_settings' => $user?->id === $game->owner_user_id,
                    'is_spectator' => ! $isMember,
                    'my_user_id' => $isMember ? $user?->id : null,
                    'classes' => $canManage ? CharacterClassDefinition::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('display_order')->get()->map(fn ($class) => [
                        'id' => $class->id, 'name' => $class->name,
                    ]) : [],
                    'players' => $canManage ? $game->members->where('status', 'active')->whereIn('role', ['player', 'co_dm'])->map(fn ($member) => [
                        'id' => $member->user->id, 'name' => $member->user->name,
                    ])->values() : [],
                    'members' => $isMember ? $game->members->map(fn ($member) => [
                        'id' => $member->id, 'name' => $member->user->name, 'role' => $member->role, 'status' => $member->status,
                    ])->values() : [],
                    'overlays' => $canManage ? $game->overlays()->orderBy('id')->get()->map(fn ($overlay) => [
                        'id' => $overlay->id, 'name' => $overlay->name, 'url' => $overlay->url(),
                    ])->values() : [],
                    'characters' => $characters->map(fn (SessionCharacter $entry) => $this->roomCharacter($entry, $user, $isMember))->values(),
                    'sheet_layouts' => [
                        'room' => $game->settings['layouts'] ?? null,
                        'system' => $canManage ? ['sheet' => app(SheetLayouts::class)->systemDefault('sheet', $game), 'card' => app(SheetLayouts::class)->systemDefault('card', $game)] : null,
                    ],
                ],
                'system' => $this->presenter->system($game, $canEditAny),
            ];

            return $isMember ? $payload : app(PublicGameState::class)->room($payload);
        });
    }

    /**
     * The personal sheet, and the targeted refresh used after a realtime notice for one character.
     *
     * @return array<string, mixed>
     */
    public function forCharacter(?User $user, GameSession $game, int $characterId, bool $withSystem = true): array
    {
        return DB::transaction(function () use ($user, $game, $characterId, $withSystem) {
            $game = GameSession::query()->with('gameSystemVersion')->findOrFail($game->id);
            $entry = $game->sessionCharacters()->where('is_active', true)->where('character_id', $characterId)
                ->with(['assignedUser', 'character'])->firstOrFail();
            $isMember = $user?->can('view', $game) ?? false;
            abort_unless($game->visibility === 'public_view' || $isMember, 403);
            $canUpdate = $isMember && ($user?->can('update', $entry->character) ?? false);

            $payload = [
                'game' => [
                    'id' => $game->id, 'name' => $game->name, 'is_spectator' => ! $isMember,
                    'current_character_id' => $game->current_character_id, 'current_revision' => $game->current_revision,
                ],
                'character' => $this->roomCharacter($entry, $user, $isMember),
            ];
            if ($withSystem) {
                $payload['system'] = $this->presenter->system($game, $canUpdate);
            }

            return $isMember ? $payload : app(PublicGameState::class)->sheet($payload);
        });
    }

    /** @return Collection<int, SessionCharacter> */
    public function activeCharacters(GameSession $game)
    {
        return $game->sessionCharacters->where('is_active', true)
            ->sortBy([fn ($a, $b) => ($a->turn_order ?? PHP_INT_MAX) <=> ($b->turn_order ?? PHP_INT_MAX), fn ($a, $b) => $a->id <=> $b->id])
            ->values();
    }

    /** @return array<string, mixed> */
    private function roomCharacter(SessionCharacter $entry, ?User $user, bool $isMember): array
    {
        $canUpdate = $isMember && ($user?->can('update', $entry->character) ?? false);

        return [
            'session_character_id' => $entry->id,
            'assigned_user_id' => $isMember ? $entry->assigned_user_id : null,
            'assigned_user_name' => $entry->assignedUser?->name,
        ] + $this->presenter->present($entry->character, $isMember, $canUpdate);
    }
}
