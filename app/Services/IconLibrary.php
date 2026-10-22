<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Str;

class IconLibrary
{
    /** @return array<int, array<string, string>> */
    public function catalog(): array
    {
        return collect(config('icons'))->map(fn ($icon, $key) => [...$icon, 'key' => 'game-icon:'.$key, 'url' => '/icons/'.$key.'.svg', 'license' => 'CC BY 3.0'])->values()->all();
    }

    public function url(?string $key): ?string
    {
        if (! $key) {
            return null;
        }
        if (str_starts_with($key, 'game-icon:')) {
            $icon = substr($key, 10);

            return array_key_exists($icon, config('icons')) ? '/icons/'.$icon.'.svg' : null;
        }
        if (str_starts_with($key, 'asset:')) {
            $id = substr($key, 6);

            return Str::isUuid($id) ? '/media/'.$id : null;
        }

        return preg_match('#^catalog-icons/[a-zA-Z0-9/_.-]+$#D', $key) && ! str_contains($key, '..') ? '/storage/'.$key : null;
    }

    public function allowed(?string $key, ?int $ownerId, ?string $existing = null): bool
    {
        if (! $key || $key === $existing) {
            return true;
        }
        if (str_starts_with($key, 'game-icon:')) {
            return array_key_exists(substr($key, 10), config('icons'));
        }
        if (str_starts_with($key, 'asset:')) {
            return Asset::query()->where('public_id', substr($key, 6))->where('owner_user_id', $ownerId)->exists();
        }

        return false;
    }
}
