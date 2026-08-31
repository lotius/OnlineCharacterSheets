<?php

namespace App\Services;

use App\Models\GameSystemVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DefinitionCatalog
{
    /** @return array<string, array{label: string, model: class-string<Model>}> */
    public function types(): array
    {
        return config('catalog.types');
    }

    /** @return class-string<Model> */
    public function model(string $type): string
    {
        abort_unless(isset($this->types()[$type]), 404);

        return $this->types()[$type]['model'];
    }

    public function validate(array $input, string $type, GameSystemVersion $version, ?User $owner, ?Model $definition = null): array
    {
        if (blank($input['key'] ?? null) && filled($input['name'] ?? null)) {
            $input['key'] = Str::slug($input['name'], '_');
        }

        $model = $this->model($type);
        $scopeKey = $owner ? "user:{$owner->id}" : 'system';
        $table = (new $model)->getTable();
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'key' => [
                'nullable', 'string', 'max:120', 'regex:/^[a-z0-9_]+$/',
                Rule::unique($table, 'key')->where(fn ($query) => $query
                    ->where('game_system_version_id', $version->id)
                    ->where('scope_key', $scopeKey))->ignore($definition?->getKey()),
            ],
            'configuration_json' => ['nullable', 'json'],
        ];

        if (in_array($type, ['items', 'spells', 'skills', 'classes', 'stats'], true)) {
            $rules['description'] = ['nullable', 'string', 'max:5000'];
        }
        if (in_array($type, ['items', 'spells', 'skills', 'stats', 'slots', 'trays'], true)) {
            $rules['icon'] = ['nullable', 'string', 'max:255'];
        }
        if (in_array($type, ['classes', 'stats', 'slots', 'trays'], true)) {
            $rules['display_order'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        if ($type === 'stats') {
            $rules += ['data_type' => ['required', Rule::in(['integer', 'decimal', 'text', 'boolean'])], 'default_value' => ['nullable'], 'default_max_value' => ['nullable'], 'minimum_value' => ['nullable', 'numeric'], 'maximum_value' => ['nullable', 'numeric'], 'is_public' => ['boolean']];
        } elseif ($type === 'slots') {
            $rules += ['capacity' => ['required', 'integer', 'min:1', 'max:100']];
        } elseif ($type === 'trays') {
            $rules += ['is_collapsible' => ['boolean'], 'default_open' => ['boolean']];
        }

        $data = validator($input, $rules)->validate();
        $data['configuration'] = filled($data['configuration_json'] ?? null)
            ? json_decode($data['configuration_json'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return Arr::except($data, 'configuration_json');
    }

    public function save(array $input, string $type, GameSystemVersion $version, ?User $owner, ?Model $definition = null): Model
    {
        $attributes = $this->validate($input, $type, $version, $owner, $definition) + [
            'game_system_version_id' => $version->id,
            'owner_user_id' => $owner?->id,
            'scope_key' => $owner ? "user:{$owner->id}" : 'system',
        ];
        $definition ??= new ($this->model($type));
        $definition->fill($attributes)->save();

        return $definition;
    }
}
