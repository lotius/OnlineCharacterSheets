<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSpell extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_used' => 'boolean', 'state' => 'array'];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SpellDefinition::class, 'spell_definition_id');
    }
}
