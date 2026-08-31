<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasDefinitionScope
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeSystemWide(Builder $query): Builder
    {
        return $query->whereNull('owner_user_id');
    }

    public function scopeAvailableToUser(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $scope) => $scope
            ->whereNull('owner_user_id')
            ->orWhere('owner_user_id', $user->id));
    }
}
