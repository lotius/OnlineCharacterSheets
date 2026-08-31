<?php

namespace App\Http\Controllers;

use App\Events\RoomRealtimePinged;
use App\Models\GameSession;
use Illuminate\Http\RedirectResponse;

class RoomRealtimeController extends Controller
{
    public function store(GameSession $game): RedirectResponse
    {
        $this->authorize('view', $game);
        RoomRealtimePinged::dispatch($game);

        return back()->with('success', 'Realtime ping sent.');
    }
}
