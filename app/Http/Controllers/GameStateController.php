<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameSession;
use App\Services\CharacterSheetState;
use App\Services\RoomState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameStateController extends Controller
{
    public function show(Request $request, GameSession $game, RoomState $state): JsonResponse
    {
        return response()->json($state->forViewer($request->user(), $game))->header('Cache-Control', 'private, no-store');
    }

    public function character(Request $request, GameSession $game, Character $character, CharacterSheetState $state): JsonResponse
    {
        return response()->json($state->forViewer($request->user(), $game, $character))->header('Cache-Control', 'private, no-store');
    }
}
