<?php

namespace App\Actions\GameSessions;

use App\Events\RoomRealtimePinged;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeTurn
{
    public function handle(GameSession $game, User $actor, string $direction, ?int $characterId = null, ?int $undoEventId = null): void
    {
        Gate::forUser($actor)->authorize('update', $game);
        DB::transaction(function () use ($game, $actor, $direction, $characterId, $undoEventId) {
            $game = GameSession::query()->lockForUpdate()->findOrFail($game->id);
            Gate::forUser($actor)->authorize('update', $game);
            $ids = $game->sessionCharacters()->where('is_active', true)
                ->orderByRaw('turn_order IS NULL')->orderBy('turn_order')->orderBy('id')
                ->pluck('character_id')->all();
            $before = ['current_character_id' => $game->current_character_id];
            $target = null;
            if ($undoEventId !== null) {
                $target = GameEvent::query()->where('game_session_id', $game->id)->findOrFail($undoEventId);
                $latestTurn = GameEvent::query()->where('game_session_id', $game->id)
                    ->whereNull('character_id')->whereIn('event_type', ['turn.changed', 'turn.cleared', 'event.undone'])->latest('revision')->first();
                if ($target->event_type !== 'turn.changed' || $target->compensation()->exists()
                    || $latestTurn?->id !== $target->id || $target->after_state !== $before) {
                    throw ValidationException::withMessages(['turn' => 'This turn can no longer be safely undone.']);
                }
                $characterId = $target->before_state['current_character_id'];
            } elseif ($direction !== 'select') {
                if ($ids === []) {
                    throw ValidationException::withMessages(['turn' => 'Add a character before starting turns.']);
                }
                $position = array_search($game->current_character_id, $ids, true);
                $index = $position === false ? null : (int) $position;
                $characterId = match ($direction) {
                    'first' => $ids[0],
                    'previous' => $ids[$index === null ? count($ids) - 1 : ($index - 1 + count($ids)) % count($ids)],
                    default => $ids[$index === null ? 0 : ($index + 1) % count($ids)],
                };
            }
            if (($characterId === null && $target === null) || ($characterId !== null && ! in_array($characterId, $ids, true))) {
                throw ValidationException::withMessages(['turn' => 'Choose an active character in this room.']);
            }
            if ($game->current_character_id === $characterId) {
                return;
            }
            $game->update(['current_character_id' => $characterId, 'current_revision' => $game->current_revision + 1]);
            GameEvent::query()->create([
                'game_session_id' => $game->id, 'revision' => $game->current_revision,
                'actor_user_id' => $actor->id, 'event_type' => $target ? 'event.undone' : 'turn.changed',
                'before_state' => $before, 'after_state' => ['current_character_id' => $characterId],
                'undoes_event_id' => $target?->id,
            ]);
            DB::afterCommit(fn () => RoomRealtimePinged::notify($game));
        });
    }
}
