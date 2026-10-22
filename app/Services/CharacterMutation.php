<?php

namespace App\Services;

use App\Events\RoomRealtimePinged;
use App\Models\Character;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CharacterMutation
{
    public function __construct(private CharacterEventState $states) {}

    /**
     * @template T
     *
     * @param  callable(): T  $mutate
     * @return T
     */
    public function run(Character $character, string $eventType, string $entityType, ?int $entityId, callable $mutate): mixed
    {
        return DB::transaction(function () use ($character, $eventType, $entityType, $entityId, $mutate) {
            $character = Character::query()->lockForUpdate()->findOrFail($character->id);
            $rooms = $this->rooms($character);
            Gate::authorize('update', $character);
            $before = $this->states->read($character, $entityType, $entityId);
            if ($entityId !== null && $before === [] && $entityType !== 'note') {
                throw ValidationException::withMessages(['event' => 'The affected record no longer exists.']);
            }
            $result = $mutate();
            if ($entityId === null && $result instanceof Model) {
                $entityId = $result->getKey();
                // firstOrCreate may have returned an existing ability: that is a no-op.
                if (! $result->wasRecentlyCreated) {
                    return $result;
                }
            }
            $after = $this->states->read($character, $entityType, $entityId);
            if ($before !== $after) {
                $this->record($rooms, $character, $eventType, $entityType, $entityId, $before, $after, auth()->id());
            }

            return $result;
        });
    }

    public function undo(GameSession $game, GameEvent $event, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $game);
        abort_unless($event->game_session_id === $game->id, 404);
        DB::transaction(function () use ($game, $event, $actor) {
            $character = Character::query()->lockForUpdate()->findOrFail($event->character_id);
            $rooms = $this->rooms($character);
            Gate::forUser($actor)->authorize('update', $game);
            Gate::forUser($actor)->authorize('update', $character);
            if (! $rooms->contains('id', $game->id)) {
                throw ValidationException::withMessages(['event' => 'This character is no longer active in this room.']);
            }
            $event->refresh();
            $latest = GameEvent::query()->where('entity_type', $event->entity_type)->where('entity_id', $event->entity_id)->latest('id')->first();
            if (! $event->is_undoable || ! $event->mutation_id || $event->compensation()->exists() || $latest?->mutation_id !== $event->mutation_id) {
                throw ValidationException::withMessages(['event' => 'This change can no longer be safely undone.']);
            }
            $current = $this->states->read($character, $event->entity_type, $event->entity_id);
            if (! $this->states->same($current, $event->after_state)) {
                throw ValidationException::withMessages(['event' => 'The current state has changed since this event.']);
            }
            $this->states->restore($character, $event->entity_type, $event->entity_id, $event->before_state);
            $this->record($rooms, $character, 'event.undone', $event->entity_type, $event->entity_id, $current, $event->before_state, $actor->id, $event->mutation_id);
        });
    }

    /** @return Collection<int, GameSession> */
    private function rooms(Character $character): Collection
    {
        return GameSession::query()->whereIn('id', $character->sessions()->where('is_active', true)->select('game_session_id'))->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  Collection<int, GameSession>  $rooms
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function record(Collection $rooms, Character $character, string $eventType, string $entityType, int $entityId, array $before, array $after, ?int $actorId, ?string $undoMutation = null): void
    {
        $mutationId = (string) Str::uuid();
        $character->increment('revision');
        foreach ($rooms as $room) {
            $original = $undoMutation ? GameEvent::query()->where('game_session_id', $room->id)->where('mutation_id', $undoMutation)->first() : null;
            $room->increment('current_revision');
            GameEvent::query()->create([
                'game_session_id' => $room->id, 'revision' => $room->current_revision,
                'actor_user_id' => $actorId, 'character_id' => $character->id,
                'event_type' => $eventType, 'entity_type' => $entityType, 'entity_id' => $entityId,
                'mutation_id' => $mutationId, 'is_undoable' => $undoMutation === null,
                'before_state' => $before, 'after_state' => $after, 'undoes_event_id' => $original?->id,
            ]);
            DB::afterCommit(fn () => RoomRealtimePinged::dispatch($room, false, $character->id, $character->revision));
        }
    }
}
