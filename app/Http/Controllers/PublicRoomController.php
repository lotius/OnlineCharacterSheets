<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicRoomController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('PublicRooms/Index', [
            'rooms' => GameSession::query()
                ->where('visibility', 'public_view')
                ->where('status', '!=', 'ended')
                ->with('gameSystemVersion.gameSystem')
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('gameSystemVersion.gameSystem', fn ($system) => $system->where('name', 'like', "%{$search}%"));
                }))
                ->latest()
                ->paginate(50)
                ->withQueryString()
                ->through(fn (GameSession $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'system' => $game->gameSystemVersion->gameSystem->name,
                ]),
            'filters' => ['search' => $search],
            'notice' => $request->filled('room_closed_name')
                ? sprintf('"%s" is no longer public.', $request->string('room_closed_name')->toString())
                : null,
        ]);
    }
}
