<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Inertia\Inertia;
use Inertia\Response;

class PublicRoomController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('PublicRooms/Index', [
            'rooms' => GameSession::query()
                ->where('visibility', 'public_view')
                ->where('status', '!=', 'ended')
                ->with('gameSystemVersion.gameSystem')
                ->latest()
                ->get()
                ->map(fn (GameSession $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'status' => $game->status,
                    'system' => $game->gameSystemVersion->gameSystem->name,
                ]),
        ]);
    }
}
