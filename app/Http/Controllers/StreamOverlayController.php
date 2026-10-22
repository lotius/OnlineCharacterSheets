<?php

namespace App\Http\Controllers;

use App\Events\RoomRealtimePinged;
use App\Models\GameSession;
use App\Models\StreamOverlay;
use App\Services\OverlayConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** DM-only overlay management. Rendering happens through the token routes in OverlayRenderController. */
class StreamOverlayController extends Controller
{
    public function store(Request $request, GameSession $game, OverlayConfig $config): RedirectResponse
    {
        $this->authorize('update', $game);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'preset' => ['required', Rule::in(array_keys(OverlayConfig::PRESETS))],
        ]);
        $overlay = new StreamOverlay(['game_session_id' => $game->id, 'name' => $data['name'], 'configuration' => $config->preset($data['preset'])]);
        $overlay->rotateToken();
        $overlay->save();

        return redirect()->route('games.overlays.edit', [$game, $overlay]);
    }

    public function edit(Request $request, GameSession $game, StreamOverlay $overlay, OverlayConfig $config): Response
    {
        $this->authorize('update', $game);
        abort_unless($overlay->game_session_id === $game->id, 404);

        return Inertia::render('Overlays/Edit', [
            'overlay' => ['id' => $overlay->id, 'name' => $overlay->name, 'url' => $overlay->url(), 'configuration' => $overlay->configuration],
            'presets' => OverlayConfig::PRESETS,
            'state' => $config->state($overlay),
            'game' => ['id' => $game->id, 'name' => $game->name],
        ]);
    }

    public function update(Request $request, GameSession $game, StreamOverlay $overlay, OverlayConfig $config): RedirectResponse
    {
        $this->authorize('update', $game);
        abort_unless($overlay->game_session_id === $game->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'configuration' => ['required', 'array'],
            'preset' => ['nullable', Rule::in(array_keys(OverlayConfig::PRESETS))],
        ]);
        $overlay->update([
            'name' => $data['name'],
            'configuration' => isset($data['preset']) ? $config->preset($data['preset']) : $config->validate($data['configuration']),
        ]);
        RoomRealtimePinged::dispatch($game->fresh());

        return back()->with('success', 'Overlay saved.');
    }

    /** Issue a new URL; anything using the old URL (and its realtime channel) stops updating immediately. */
    public function rotate(GameSession $game, StreamOverlay $overlay): RedirectResponse
    {
        $this->authorize('update', $game);
        abort_unless($overlay->game_session_id === $game->id, 404);
        $overlay->rotateToken();
        $overlay->save();

        return back()->with('success', 'New overlay URL issued. Update your browser source.');
    }

    public function destroy(GameSession $game, StreamOverlay $overlay): RedirectResponse
    {
        $this->authorize('update', $game);
        abort_unless($overlay->game_session_id === $game->id, 404);
        $overlay->delete();

        return redirect()->route('games.show', $game)->with('success', 'Overlay deleted.');
    }
}
