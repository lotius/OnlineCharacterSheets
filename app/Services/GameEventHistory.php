<?php

namespace App\Services;

use App\Models\Character;
use App\Models\GameEvent;

class GameEventHistory
{
    /** @return array<string, mixed> */
    public function entry(GameEvent $event): array
    {
        $turn = $event->character_id === null && in_array($event->event_type, ['turn.changed', 'turn.cleared', 'event.undone'], true);
        $latest = $turn
            ? GameEvent::query()->where('game_session_id', $event->game_session_id)->whereNull('character_id')->whereIn('event_type', ['turn.changed', 'turn.cleared', 'event.undone'])->latest('id')->value('id')
            : GameEvent::query()->where('entity_type', $event->entity_type)->where('entity_id', $event->entity_id)->latest('id')->value('mutation_id');
        $undone = $event->compensation()->exists();
        $canUndo = ! $undone && ($turn ? ($event->event_type === 'turn.changed' && $latest === $event->id) : ($event->is_undoable && $event->mutation_id === $latest));
        $detail = match ($event->entity_type) {
            'stat' => ($event->before_state['value'] ?? '—').' → '.($event->after_state['value'] ?? '—'),
            'gold' => ($event->before_state['gold'] ?? '—').' → '.($event->after_state['gold'] ?? '—'),
            'item' => ($event->before_state['quantity'] ?? 0).' → '.($event->after_state['quantity'] ?? 0).' items',
            default => null,
        };
        if ($turn) {
            $name = fn (?int $id) => $id === null ? 'Not started' : (Character::query()->find($id)?->name ?? 'Removed character');
            $detail = $name($event->before_state['current_character_id'] ?? null).' → '.$name($event->after_state['current_character_id'] ?? null);
        }

        return [
            'id' => $event->id, 'revision' => $event->revision, 'event_type' => $event->event_type,
            'character_name' => $event->character_id ? Character::query()->find($event->character_id)?->name : null,
            'detail' => $detail, 'can_undo' => $canUndo, 'undone' => $undone,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
