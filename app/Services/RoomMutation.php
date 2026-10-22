<?php

namespace App\Services;

use App\Events\PublicRoomDirectoryChanged;
use App\Events\RoomRealtimePinged;
use App\Models\GameEvent;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoomMutation
{
    /**
     * @template T
     *
     * @param  callable(): array<string, mixed>  $state
     * @param  callable(GameSession): T  $mutate
     * @return T
     */
    public function run(GameSession $game, string $type, callable $state, callable $mutate, ?int $actorId = null, bool $authorize = true): mixed
    {
        return DB::transaction(function () use ($game, $type, $state, $mutate, $actorId, $authorize) {
            $game = GameSession::query()->lockForUpdate()->findOrFail($game->id);
            if ($authorize) {
                Gate::authorize('update', $game);
            }
            $before = $state();
            $result = $mutate($game);
            $after = $state();
            if ($before !== $after) {
                $this->record($game, $type, $before, $after, $actorId ?? auth()->id());
            }

            return $result;
        });
    }

    /** Call only inside a transaction with the room locked (or just inserted).
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(GameSession $game, string $type, array $before, array $after, ?int $actorId): void
    {
        $game->increment('current_revision');
        $game->refresh();
        GameEvent::query()->create([
            'game_session_id' => $game->id, 'revision' => $game->current_revision,
            'actor_user_id' => $actorId, 'event_type' => $type,
            'entity_type' => 'room', 'entity_id' => $game->id,
            'before_state' => $before, 'after_state' => $after,
        ]);
        $wasPublic = ($before['visibility'] ?? null) === 'public_view';
        DB::afterCommit(function () use ($game, $type, $wasPublic) {
            RoomRealtimePinged::dispatch($game, $wasPublic || $game->visibility === 'public_view');
            if (in_array($type, ['session.created', 'session.updated'], true) && ($wasPublic || $game->visibility === 'public_view')) {
                PublicRoomDirectoryChanged::dispatch();
            }
        });
    }
}
