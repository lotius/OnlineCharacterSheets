<?php

namespace App\Events\Concerns;

use Illuminate\Broadcasting\BroadcastException;

/**
 * Realtime notices are hints, not the source of truth: clients also re-check revisions on a timer and on
 * reconnect. A WebSocket outage must therefore never turn an already-committed change into an HTTP error.
 */
trait BroadcastsBestEffort
{
    public static function notify(mixed ...$arguments): void
    {
        try {
            static::dispatch(...$arguments);
        } catch (BroadcastException $exception) {
            report($exception);
        }
    }
}
