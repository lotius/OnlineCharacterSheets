<?php

namespace App\Http\Controllers;

use App\Models\GameEvent;
use App\Models\GameSession;
use App\Services\GameEventHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameEventController extends Controller
{
    public function index(Request $request, GameSession $game, GameEventHistory $history): JsonResponse
    {
        $this->authorize('update', $game);

        $request->validate(['before_revision' => ['nullable', 'integer', 'min:1']]);
        $events = GameEvent::query()->where('game_session_id', $game->id)
            ->when($request->filled('before_revision'), fn ($query) => $query->where('revision', '<', $request->integer('before_revision')))
            ->latest('revision')->limit(21)->get();
        $page = $events->take(20);

        return response()->json([
            'data' => $page->map(fn ($event) => $history->entry($event))->values(),
            'next_before_revision' => $events->count() > 20 ? $page->last()->revision : null,
        ])->header('Cache-Control', 'private, no-store');
    }
}
