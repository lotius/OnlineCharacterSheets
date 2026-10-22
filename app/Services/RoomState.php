<?php

namespace App\Services;

use App\Models\CharacterClassDefinition;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\TrayDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoomState
{
    private const VIEWER_STALE_SECONDS = 45;

    /** @return array<string, mixed> */
    public function forViewer(?User $user, GameSession $game): array
    {
        return DB::transaction(function () use ($user, $game) {
            $game = GameSession::query()->lockForUpdate()->findOrFail($game->id);

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

            $payload = [
                'game' => [
                    'id' => $game->id, 'name' => $game->name, 'join_code' => $isMember ? $game->join_code : null,
                    'status' => $game->status, 'visibility' => $game->visibility,
                    'current_character_id' => $game->current_character_id,
                    'current_revision' => $game->current_revision,
                    'game_events' => $canManageMembers ? GameEvent::query()->where('game_session_id', $game->id)->latest('revision')->limit(20)->get()->map(fn ($event) => app(GameEventHistory::class)->entry($event)) : [],
                    'turn_events' => $canManageMembers ? GameEvent::query()->where('game_session_id', $game->id)->whereNull('character_id')->withExists('compensation')->latest('revision')->limit(20)->get()->map(fn ($event) => [
                        'id' => $event->id, 'revision' => $event->revision, 'event_type' => $event->event_type,
                        'before_character_id' => $event->before_state['current_character_id'] ?? null,
                        'after_character_id' => $event->after_state['current_character_id'] ?? null,
                        'undone' => $event->compensation_exists,
                    ]) : [],
                    'viewer_count' => $game->viewers()->where('last_seen_at', '>=', now()->subSeconds(self::VIEWER_STALE_SECONDS))->count(),
                    'system' => $game->gameSystemVersion->gameSystem->name,
                    'can_manage_members' => $canManageMembers,
                    'can_manage_settings' => $user?->id === $game->owner_user_id,
                    'is_spectator' => ! $isMember,
                    'classes' => $canManageMembers ? $classes->map(fn ($class) => [
                        'id' => $class->id, 'name' => $class->name,
                    ]) : [],
                    'players' => $canManageMembers ? $game->members->where('status', 'active')->whereIn('role', ['player', 'co_dm'])->map(fn ($member) => [
                        'id' => $member->user->id, 'name' => $member->user->name,
                    ])->values() : [],
                    'members' => $isMember ? $game->members->map(fn ($member) => [
                        'id' => $member->id, 'name' => $member->user->name, 'role' => $member->role, 'status' => $member->status,
                    ]) : [],
                    'characters' => $game->sessionCharacters->where('is_active', true)->sortBy([fn ($a, $b) => ($a->turn_order ?? PHP_INT_MAX) <=> ($b->turn_order ?? PHP_INT_MAX), fn ($a, $b) => $a->id <=> $b->id])->map(function ($sessionCharacter) use ($user, $trays, $isMember) {
                        $character = $sessionCharacter->character;
                        $canUpdate = $isMember && ($user?->can('update', $character) ?? false);

                        return [
                            'session_character_id' => $sessionCharacter->id,
                            'id' => $character->id,
                            'name' => $character->name,
                            'class_name' => $character->characterClass?->name,
                            'gold' => $character->gold,
                            'assigned_user_id' => $isMember ? $sessionCharacter->assigned_user_id : null,
                            'assigned_user_name' => $sessionCharacter->assignedUser?->name,
                            'can_update' => $canUpdate,
                            'stats' => $character->stats->filter(fn ($stat) => $isMember || $stat->definition->is_public)->sortBy('definition.display_order')->values()->map(fn ($stat) => [
                                'id' => $stat->id, 'key' => $stat->definition->key, 'name' => $stat->definition->name,
                                'value' => $stat->value, 'max_value' => $stat->max_value,
                            ]),
                            'trays' => $trays->map(fn ($tray) => [
                                'key' => $tray->key, 'name' => $tray->name, 'default_open' => $tray->default_open,
                            ]),
                            'items' => $character->items->map(fn ($item) => [
                                'id' => $item->id, 'name' => $item->custom_name ?? $item->definition?->name,
                                'description' => $item->custom_description ?? $item->definition?->description,
                                'icon' => $item->definition?->icon ? '/storage/'.$item->definition->icon : null,
                                'quantity' => $item->quantity,
                                'is_active' => $item->is_active,
                                'slots' => $item->slots->map(fn ($slot) => $slot->definition->name)->values(),
                            ]),
                            'spells' => $character->spells->map(fn ($spell) => [
                                'id' => $spell->id, 'name' => $spell->definition->name,
                                'description' => $spell->definition->description, 'is_used' => $spell->is_used,
                                'icon' => $spell->definition->icon ? '/storage/'.$spell->definition->icon : null,
                            ]),
                            'skills' => $character->skills->map(fn ($skill) => [
                                'id' => $skill->id, 'name' => $skill->definition->name,
                                'description' => $skill->definition->description, 'is_used' => $skill->is_used,
                                'icon' => $skill->definition->icon ? '/storage/'.$skill->definition->icon : null,
                            ]),
                            'note' => ! $character->note || (! $character->note->is_private || $canUpdate)
                                ? $character->note?->content
                                : null,
                        ];
                    })->values(),
                ],
            ];
            return $isMember ? $payload : app(PublicGameState::class)->room($payload);
        });
    }
}
