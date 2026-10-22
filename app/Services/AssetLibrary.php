<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetLibrary
{
    public function upload(User $owner, UploadedFile $file, string $name, string $type): Asset
    {
        $dimensions = getimagesize($file->getRealPath());
        if (! $dimensions) {
            throw ValidationException::withMessages(['image' => 'Choose a valid image.']);
        }
        $path = $file->store('assets/'.$owner->id, 'public');
        try {
            return Asset::query()->create([
                'public_id' => (string) Str::uuid(), 'owner_user_id' => $owner->id,
                'name' => $name, 'type' => $type, 'storage_disk' => 'public', 'storage_path' => $path,
                'mime_type' => $file->getMimeType(), 'width' => $dimensions[0], 'height' => $dimensions[1],
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    public function delete(User $owner, Asset $asset): void
    {
        abort_unless($asset->owner_user_id === $owner->id, 403);
        DB::transaction(function () use ($asset) {
            $asset = Asset::query()->lockForUpdate()->findOrFail($asset->id);
            foreach (config('catalog.types') as $type => $definition) {
                if ($type !== 'classes' && $definition['model']::query()->where('icon', 'asset:'.$asset->public_id)->exists()) {
                    throw ValidationException::withMessages(['asset' => 'This image is still used by a catalog definition. Choose another image there first.']);
                }
            }
            $asset->delete();
            DB::afterCommit(fn () => Storage::disk($asset->storage_disk)->delete($asset->storage_path));
        });
    }
}
