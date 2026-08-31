<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterSkill extends Model
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
        return $this->belongsTo(SkillDefinition::class, 'skill_definition_id');
    }
}
