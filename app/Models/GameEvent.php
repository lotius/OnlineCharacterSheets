<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array<string, mixed> $before_state
 * @property array<string, mixed> $after_state
 */
class GameEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Game events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Game events are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'is_undoable' => 'boolean'];
    }

    /** @return HasOne<GameEvent, $this> */
    public function compensation(): HasOne
    {
        return $this->hasOne(self::class, 'undoes_event_id');
    }
}
