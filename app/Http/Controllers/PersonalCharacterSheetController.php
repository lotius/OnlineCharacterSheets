<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameSession;
use App\Services\RoomState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalCharacterSheetController extends Controller
{
    public function show(Request $request, GameSession $game, Character $character, RoomState $state): Response
    {
        return Inertia::render('Characters/Show', $state->forCharacter($request->user(), $game, $character->id));
    }
}
