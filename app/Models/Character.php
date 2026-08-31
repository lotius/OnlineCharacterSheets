<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function gameSystemVersion(): BelongsTo
    {
        return $this->belongsTo(GameSystemVersion::class);
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClassDefinition::class, 'character_class_definition_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(CharacterStat::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(SessionCharacter::class);
    }
}
