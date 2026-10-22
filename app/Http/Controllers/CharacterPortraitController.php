<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Services\AssetLibrary;
use App\Services\CharacterMutation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterPortraitController extends Controller
{
    public function store(Request $request, Character $character, AssetLibrary $library): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $request->validate(['image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:5120', 'dimensions:max_width=4096,max_height=4096']]);
        $asset = $library->upload($request->user(), $request->file('image'), "{$character->name} portrait", 'portrait');
        app(CharacterMutation::class)->run($character, 'portrait.changed', 'portrait', $character->id, fn () => $character->refresh()->update(['portrait' => 'asset:'.$asset->public_id]));

        return $this->characterResponse($request, $character, success: 'Portrait updated.');
    }

    public function destroy(Request $request, Character $character): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        app(CharacterMutation::class)->run($character, 'portrait.changed', 'portrait', $character->id, fn () => $character->refresh()->update(['portrait' => null]));

        return $this->characterResponse($request, $character, success: 'Portrait removed.');
    }
}
