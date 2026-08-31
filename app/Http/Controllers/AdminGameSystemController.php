<?php

namespace App\Http\Controllers;

use App\Models\GameSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminGameSystemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Systems', [
            'systems' => GameSystem::query()->with(['versions' => fn ($query) => $query->orderByDesc('is_current')->orderBy('version')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:game_systems,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_theme' => ['nullable', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:40'],
            'version_name' => ['nullable', 'string', 'max:120'],
        ]);

        $version = DB::transaction(function () use ($data) {
            $system = GameSystem::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'default_theme' => $data['default_theme'] ?? null,
                'is_active' => true,
            ]);

            return $system->versions()->create(['version' => $data['version'], 'name' => $data['version_name'] ?? null, 'is_current' => true]);
        });

        return redirect()->route('admin.catalog', ['version' => $version->id])->with('success', 'Game system created.');
    }

    public function storeVersion(Request $request, GameSystem $system): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('game_system_versions')->where('game_system_id', $system->id)],
            'name' => ['nullable', 'string', 'max:120'],
            'is_current' => ['boolean'],
        ]);

        $version = DB::transaction(function () use ($system, $data) {
            if ($data['is_current'] ?? false) {
                $system->versions()->update(['is_current' => false]);
            }

            return $system->versions()->create($data);
        });

        return redirect()->route('admin.catalog', ['version' => $version->id])->with('success', 'Version created.');
    }
}
