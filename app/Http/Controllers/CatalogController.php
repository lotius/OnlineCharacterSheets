<?php

namespace App\Http\Controllers;

use App\Models\GameSystemVersion;
use App\Services\DefinitionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function personal(Request $request, DefinitionCatalog $catalog): Response
    {
        return $this->render($request, $catalog, $request->user());
    }

    public function admin(Request $request, DefinitionCatalog $catalog): Response
    {
        return $this->render($request, $catalog, null, true);
    }

    public function storePersonal(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): RedirectResponse
    {
        $catalog->save($request->all(), $type, $version, $request->user());

        return back()->with('success', 'Homebrew definition created.');
    }

    public function storeAdmin(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): RedirectResponse
    {
        $catalog->save($request->all(), $type, $version, null);

        return back()->with('success', 'System definition created.');
    }

    public function updatePersonal(Request $request, GameSystemVersion $version, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $model = $this->ownedDefinition($catalog, $type, $definition, $request->user()->id);
        abort_unless((int) $model->game_system_version_id === $version->id, 404);
        $catalog->save($request->all(), $type, $version, $request->user(), $model);

        return back()->with('success', 'Homebrew definition updated.');
    }

    public function updateAdmin(Request $request, GameSystemVersion $version, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $model = $this->ownedDefinition($catalog, $type, $definition, null);
        abort_unless((int) $model->game_system_version_id === $version->id, 404);
        $catalog->save($request->all(), $type, $version, null, $model);

        return back()->with('success', 'System definition updated.');
    }

    public function destroyPersonal(Request $request, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $this->ownedDefinition($catalog, $type, $definition, $request->user()->id)->delete();

        return back()->with('success', 'Homebrew definition deleted.');
    }

    public function destroyAdmin(string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $this->ownedDefinition($catalog, $type, $definition, null)->delete();

        return back()->with('success', 'System definition deleted.');
    }

    private function render(Request $request, DefinitionCatalog $catalog, mixed $owner, bool $isAdmin = false): Response
    {
        $versions = GameSystemVersion::query()->with('gameSystem')->orderBy('game_system_id')->orderBy('version')->get();
        $selected = $versions->firstWhere('id', $request->integer('version')) ?? $versions->first();
        $groups = [];

        if ($selected) {
            foreach ($catalog->types() as $key => $type) {
                $groups[$key] = [
                    'label' => $type['label'],
                    'definitions' => $type['model']::query()
                        ->where('game_system_version_id', $selected->id)
                        ->where('owner_user_id', $owner?->id)
                        ->orderBy('display_order')
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Model $definition) => [
                            ...$definition->toArray(),
                            'configuration_json' => $definition->configuration ? json_encode($definition->configuration, JSON_PRETTY_PRINT) : '',
                        ]),
                ];
            }
        }

        return Inertia::render('Catalog/Index', [
            'mode' => $isAdmin ? 'admin' : 'personal',
            'versions' => $versions->map(fn ($version) => ['id' => $version->id, 'name' => $version->gameSystem->name.' — '.($version->name ?? $version->version)]),
            'selectedVersionId' => $selected?->id,
            'groups' => $groups,
        ]);
    }

    private function ownedDefinition(DefinitionCatalog $catalog, string $type, int $id, ?int $ownerId): Model
    {
        return $catalog->model($type)::query()->where('owner_user_id', $ownerId)->findOrFail($id);
    }
}
