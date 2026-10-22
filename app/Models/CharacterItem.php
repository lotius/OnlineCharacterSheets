<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacterItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ItemDefinition::class, 'item_definition_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(CharacterItemSlot::class);
    }
}
