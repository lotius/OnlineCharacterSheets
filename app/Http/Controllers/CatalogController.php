<?php

namespace App\Http\Controllers;

use App\Models\GameSystemVersion;
use App\Models\User;
use App\Services\DefinitionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $catalog->save([...$request->all(), 'icon_upload' => $request->file('icon_upload')], $type, $version, $request->user());

        return back()->with('success', 'Homebrew definition created.');
    }

    public function storeAdmin(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): RedirectResponse
    {
        $catalog->save([...$request->all(), 'icon_upload' => $request->file('icon_upload')], $type, $version, null);

        return back()->with('success', 'System definition created.');
    }

    public function importPersonal(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): RedirectResponse
    {
        return $this->import($request, $version, $type, $catalog, $request->user());
    }

    public function importAdmin(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): RedirectResponse
    {
        return $this->import($request, $version, $type, $catalog, null);
    }

    public function exportPersonal(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog): StreamedResponse
    {
        return $this->export($version, $type, $catalog, $request->user());
    }

    public function exportAdmin(GameSystemVersion $version, string $type, DefinitionCatalog $catalog): StreamedResponse
    {
        return $this->export($version, $type, $catalog, null);
    }

    public function updatePersonal(Request $request, GameSystemVersion $version, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $model = $this->ownedDefinition($catalog, $type, $definition, $request->user()->id);
        abort_unless((int) $model->game_system_version_id === $version->id, 404);
        $catalog->save([...$request->all(), 'icon_upload' => $request->file('icon_upload')], $type, $version, $request->user(), $model);

        return back()->with('success', 'Homebrew definition updated.');
    }

    public function updateAdmin(Request $request, GameSystemVersion $version, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $model = $this->ownedDefinition($catalog, $type, $definition, null);
        abort_unless((int) $model->game_system_version_id === $version->id, 404);
        $catalog->save([...$request->all(), 'icon_upload' => $request->file('icon_upload')], $type, $version, null, $model);

        return back()->with('success', 'System definition updated.');
    }

    public function destroyPersonal(Request $request, string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $catalog->delete($this->ownedDefinition($catalog, $type, $definition, $request->user()->id));

        return back()->with('success', 'Homebrew definition deleted.');
    }

    public function destroyAdmin(string $type, int $definition, DefinitionCatalog $catalog): RedirectResponse
    {
        $catalog->delete($this->ownedDefinition($catalog, $type, $definition, null));

        return back()->with('success', 'System definition deleted.');
    }

    private function render(Request $request, DefinitionCatalog $catalog, mixed $owner, bool $isAdmin = false): Response
    {
        $versions = GameSystemVersion::query()->with('gameSystem')->orderBy('game_system_id')->orderBy('version')->get();
        $selected = $versions->firstWhere('id', $request->integer('version')) ?? $versions->first();
        $groups = [];

        if ($selected) {
            foreach ($catalog->types() as $key => $type) {
                $definitions = $type['model']::query()
                    ->where('game_system_version_id', $selected->id)
                    ->where('owner_user_id', $owner?->id);

                if (in_array($key, ['classes', 'stats', 'slots', 'trays'], true)) {
                    $definitions->orderBy('display_order');
                }

                $groups[$key] = [
                    'label' => $type['label'],
                    'definitions' => $definitions
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
            'versions' => $versions->map(fn ($version) => ['id' => $version->id, 'name' => $version->display_name]),
            'selectedVersionId' => $selected?->id,
            'groups' => $groups,
        ]);
    }

    private function ownedDefinition(DefinitionCatalog $catalog, string $type, int $id, ?int $ownerId): Model
    {
        return $catalog->model($type)::query()->where('owner_user_id', $ownerId)->findOrFail($id);
    }

    private function import(Request $request, GameSystemVersion $version, string $type, DefinitionCatalog $catalog, ?User $owner): RedirectResponse
    {
        $catalog->model($type);
        $request->validate([
            'catalog_file' => ['required', 'file', 'max:2048', 'extensions:json'],
        ]);

        try {
            $records = json_decode($request->file('catalog_file')->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['catalog_file' => 'The uploaded file must contain valid JSON.']);
        }

        if (is_array($records) && isset($records['records'])) {
            if (isset($records['definition_type']) && $records['definition_type'] !== $type) {
                throw ValidationException::withMessages(['catalog_file' => 'This export contains '.$records['definition_type'].' definitions, not '.$type.'.']);
            }

            $records = $records['records'];
        }

        if (! is_array($records) || ! array_is_list($records)) {
            throw ValidationException::withMessages(['catalog_file' => 'The JSON must be a top-level array or an export object containing a records array.']);
        }

        if (count($records) > 500) {
            throw ValidationException::withMessages(['catalog_file' => 'A catalog import may contain at most 500 records.']);
        }

        $model = $catalog->model($type);
        $ownerId = $owner?->id;

        DB::transaction(function () use ($records, $catalog, $type, $version, $model, $owner, $ownerId): void {
            foreach ($records as $index => $record) {
                if (! is_array($record)) {
                    throw ValidationException::withMessages(['catalog_file' => 'Record '.($index + 1).' must be a JSON object.']);
                }

                $configuration = is_array($record['configuration'] ?? null) ? $record['configuration'] : [];
                foreach (['category', 'charges', 'required_slots', 'default_stats', 'starting_equipment'] as $key) {
                    if (array_key_exists($key, $record)) {
                        $configuration[$key] = $record[$key];
                    }
                }

                $input = Arr::except($record, ['configuration', 'category', 'charges', 'required_slots', 'default_stats', 'starting_equipment']);
                $input['configuration_json'] = json_encode($configuration, JSON_THROW_ON_ERROR);
                $key = $input['key'] ?? null;
                $definition = filled($key)
                    ? $model::query()
                        ->where('game_system_version_id', $version->id)
                        ->where('owner_user_id', $ownerId)
                        ->where('key', $key)
                        ->first()
                    : null;

                try {
                    $catalog->save($input, $type, $version, $owner, $definition);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages([
                        'catalog_file' => 'Record '.($index + 1).': '.collect($exception->errors())->flatten()->first(),
                    ]);
                }
            }
        });

        return back()->with('success', count($records).' catalog definitions imported.');
    }

    private function export(GameSystemVersion $version, string $type, DefinitionCatalog $catalog, ?User $owner): StreamedResponse
    {
        $model = $catalog->model($type);
        $records = $model::query()
            ->where('game_system_version_id', $version->id)
            ->where('owner_user_id', $owner?->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Model $definition) => Arr::only($definition->toArray(), $this->exportFields($type)))
            ->values();
        $payload = [
            'format_version' => 1,
            'definition_type' => $type,
            'game_system_version' => [
                'id' => $version->id,
                'name' => $version->loadMissing('gameSystem')->display_name,
            ],
            'fields' => $this->fieldDocumentation($type),
            'records' => $records,
        ];
        $filename = (string) str($version->display_name)->slug()->append("-{$type}.json");

        return response()->streamDownload(
            fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    /** @return list<string> */
    private function exportFields(string $type): array
    {
        $fields = ['key', 'name'];

        if (in_array($type, ['items', 'spells', 'skills', 'classes', 'stats'], true)) {
            $fields[] = 'description';
        }
        if (in_array($type, ['items', 'spells', 'skills', 'stats', 'slots', 'trays'], true)) {
            $fields[] = 'icon';
        }
        if (in_array($type, ['classes', 'stats', 'slots', 'trays'], true)) {
            $fields[] = 'display_order';
        }

        return [...$fields, ...match ($type) {
            'stats' => ['data_type', 'default_value', 'default_max_value', 'minimum_value', 'maximum_value', 'is_public', 'configuration'],
            'slots' => ['capacity', 'configuration'],
            'trays' => ['is_collapsible', 'default_open', 'configuration'],
            default => ['configuration'],
        }];
    }

    /** @return array{required: array<string, string>, optional: array<string, string>} */
    private function fieldDocumentation(string $type): array
    {
        $optional = [
            'key' => 'Stable lowercase identifier; generated from name when omitted.',
            'configuration' => 'Object containing additional game-system-specific settings.',
        ];

        if (in_array($type, ['items', 'spells', 'skills', 'classes', 'stats'], true)) {
            $optional['description'] = 'Display description, up to 5000 characters.';
        }
        if (in_array($type, ['items', 'spells', 'skills', 'stats', 'slots', 'trays'], true)) {
            $optional['icon'] = 'Existing stored icon path; use the catalog editor to upload a new image.';
        }
        if (in_array($type, ['classes', 'stats', 'slots', 'trays'], true)) {
            $optional['display_order'] = 'Non-negative integer controlling display order.';
        }

        $optional += match ($type) {
            'items' => ['category' => 'Convenience field stored in configuration.category.', 'required_slots' => 'Array of equipment-slot keys stored in configuration.required_slots.'],
            'spells', 'skills' => ['category' => 'Convenience field stored in configuration.category.', 'charges' => 'Non-negative default charge count stored in configuration.charges.'],
            'classes' => ['default_stats' => 'Object mapping stat keys to starting values.', 'starting_equipment' => 'Array of item keys granted when a character is created.'],
            'stats' => ['data_type' => 'integer, decimal, text, or boolean.', 'default_value' => 'Initial value.', 'default_max_value' => 'Initial maximum value.', 'minimum_value' => 'Allowed minimum.', 'maximum_value' => 'Allowed maximum.', 'is_public' => 'Whether spectators may see the stat.'],
            'slots' => ['capacity' => 'Maximum number of equipped items; defaults to 1.'],
            'trays' => ['is_collapsible' => 'Whether the section may collapse.', 'default_open' => 'Whether the section starts open.'],
            default => [],
        };

        return ['required' => ['name' => 'Human-readable display name.'], 'optional' => $optional];
    }
}
