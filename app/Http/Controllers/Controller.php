<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Services\CharacterPresenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Sheet clients send JSON requests and reconcile their optimistic state with the returned character;
     * classic form posts keep the Inertia redirect.
     *
     * @param  list<string>  $warnings
     */
    protected function characterResponse(Request $request, Character $character, array $warnings = [], ?string $success = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            $response = back();
            if ($warnings !== []) {
                $response = $response->with('warning', implode(' ', $warnings));
            }

            return $success ? $response->with('success', $success) : $response;
        }

        $character = Character::query()->findOrFail($character->id);

        return response()->json([
            'character' => app(CharacterPresenter::class)->present($character, true, $request->user()->can('update', $character)),
            'warnings' => $warnings,
        ]);
    }
}
