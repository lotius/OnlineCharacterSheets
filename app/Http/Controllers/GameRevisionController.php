<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameRevisionController extends Controller
{
    public function show(Request $request, GameSession $game): JsonResponse
    {
        $isMember = $request->user()?->can('view', $game) ?? false;
        abort_unless($game->visibility === 'public_view' || $isMember, 403);
        $request->validate(['character_id' => ['nullable', 'integer']]);
        if ($request->filled('character_id')) {
            abort_unless($game->sessionCharacters()->where('is_active', true)->where('character_id', $request->integer('character_id'))->exists(), 404);
        }

        return response()->json(['game_id' => $game->id, 'revision' => $game->current_revision, 'is_spectator' => ! $isMember])->header('Cache-Control', 'private, no-store');
    }
}
