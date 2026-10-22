<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameSession;
use App\Services\RoomState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameStateController extends Controller
{
    public function show(Request $request, GameSession $game, RoomState $state): JsonResponse
    {
        return response()->json($state->forViewer($request->user(), $game))->header('Cache-Control', 'private, no-store');
    }

    /** Targeted refresh after a realtime notice; `?system=0` skips the shared catalog context. */
    public function character(Request $request, GameSession $game, Character $character, RoomState $state): JsonResponse
    {
        return response()->json($state->forCharacter($request->user(), $game, $character->id, $request->boolean('system', true)))->header('Cache-Control', 'private, no-store');
    }
}
