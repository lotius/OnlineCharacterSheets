<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Services\RoomMutation;
use App\Services\SheetLayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Room-level sheet/card layout overrides. Null for a layout means "use the game system default". */
class GameLayoutController extends Controller
{
    public function update(Request $request, GameSession $game, SheetLayouts $layouts, RoomMutation $mutation): RedirectResponse
    {
        $this->authorize('update', $game);
        $request->validate(['sheet' => ['nullable', 'array'], 'card' => ['nullable', 'array']]);
        $value = [];
        foreach (SheetLayouts::NAMES as $name) {
            if ($request->input($name) !== null) {
                $value[$name] = $layouts->validate($request->input($name), $name);
            }
        }

        $mutation->run($game, 'session.layouts_changed', fn () => ['layouts' => $game->fresh()->settings['layouts'] ?? null], function (GameSession $locked) use ($value) {
            $settings = $locked->settings ?? [];
            if ($value === []) {
                unset($settings['layouts']);
            } else {
                $settings['layouts'] = $value;
            }
            $locked->update(['settings' => $settings]);
        });

        return back()->with('success', 'Sheet layout saved.');
    }
}
