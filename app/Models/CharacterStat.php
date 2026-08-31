<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterStat extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json', 'max_value' => 'json'];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(StatDefinition::class, 'stat_definition_id');
    }
}
