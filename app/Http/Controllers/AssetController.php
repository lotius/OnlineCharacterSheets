<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\AssetLibrary;
use App\Services\IconLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function index(Request $request, IconLibrary $icons): Response
    {
        return Inertia::render('Assets', [
            'assets' => Asset::query()->where('owner_user_id', $request->user()->id)->latest()->paginate(24)->through(fn ($asset) => [
                'id' => $asset->id, 'name' => $asset->name, 'type' => $asset->type, 'url' => '/media/'.$asset->public_id,
                'key' => 'asset:'.$asset->public_id, 'width' => $asset->width, 'height' => $asset->height,
            ]),
            'icons' => $icons->catalog(),
        ]);
    }

    public function store(Request $request, AssetLibrary $library): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['portrait', 'icon', 'overlay_image', 'frame', 'background', 'logo', 'theme_asset'])],
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp,gif', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
        ]);
        $library->upload($request->user(), $request->file('image'), $data['name'], $data['type']);

        return back()->with('success', 'Image uploaded.');
    }

    public function destroy(Request $request, Asset $asset, AssetLibrary $library): RedirectResponse
    {
        $library->delete($request->user(), $asset);

        return back()->with('success', 'Image removed.');
    }

    public function file(Asset $asset): StreamedResponse
    {
        return Storage::disk($asset->storage_disk)->response($asset->storage_path, null, [
            'Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
