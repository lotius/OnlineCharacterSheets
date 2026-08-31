<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_private' => 'boolean'];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
