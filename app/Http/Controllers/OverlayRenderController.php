<?php

namespace App\Http\Controllers;

use App\Models\StreamOverlay;
use App\Services\OverlayConfig;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Token-authenticated, read-only browser-source routes. There are deliberately no mutation routes here;
 * the token never establishes a session and only reaches the filtered overlay state.
 */
class OverlayRenderController extends Controller
{
    public function show(string $token, OverlayConfig $config): Response
    {
        $overlay = $this->overlay($token);

        return Inertia::render('Overlays/Show', ['token' => $token, 'state' => $config->state($overlay)]);
    }

    public function state(string $token, OverlayConfig $config): JsonResponse
    {
        return response()->json($config->state($this->overlay($token)))->header('Cache-Control', 'no-store');
    }

    private function overlay(string $token): StreamOverlay
    {
        $overlay = StreamOverlay::findByToken($token);
        abort_unless($overlay, 404);
        if (! $overlay->last_used_at || $overlay->last_used_at->lt(now()->subMinutes(5))) {
            $overlay->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $overlay;
    }
}
