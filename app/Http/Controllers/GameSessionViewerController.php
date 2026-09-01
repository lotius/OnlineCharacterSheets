<?php

namespace App\Http\Controllers;

use App\Events\RoomViewerCountChanged;
use App\Models\GameSession;
use App\Models\GameSessionViewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameSessionViewerController extends Controller
{
    public function store(Request $request, GameSession $game): JsonResponse
    {
        $user = $request->user();
        $isMember = $user?->can('view', $game) ?? false;

        abort_unless($game->visibility === 'public_view' || $isMember, 403);

        $viewerKey = $user ? "user:{$user->id}" : 'anon:'.$request->session()->getId();
        $now = now();
        $staleBefore = $now->copy()->subSeconds(45);

        $viewerCount = DB::transaction(function () use ($game, $user, $viewerKey, $now, $staleBefore): int {
            GameSessionViewer::query()
                ->where('game_session_id', $game->id)
                ->where('last_seen_at', '<', $staleBefore)
                ->delete();

            GameSessionViewer::query()->updateOrCreate(
                ['game_session_id' => $game->id, 'viewer_key' => $viewerKey],
                ['user_id' => $user?->id, 'last_seen_at' => $now],
            );

            return GameSessionViewer::query()
                ->where('game_session_id', $game->id)
                ->where('last_seen_at', '>=', $staleBefore)
                ->count();
        });

        broadcast(new RoomViewerCountChanged($game, $viewerCount))->toOthers();

        return response()->json(['viewer_count' => $viewerCount]);
    }
}
