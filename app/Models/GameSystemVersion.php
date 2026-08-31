<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSystemVersion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'is_current' => 'boolean'];
    }

    public function gameSystem(): BelongsTo
    {
        return $this->belongsTo(GameSystem::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(StatDefinition::class)->whereNull('owner_user_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(SlotDefinition::class)->whereNull('owner_user_id');
    }

    public function trays(): HasMany
    {
        return $this->hasMany(TrayDefinition::class)->whereNull('owner_user_id');
    }

    public function characterClasses(): HasMany
    {
        return $this->hasMany(CharacterClassDefinition::class)->whereNull('owner_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemDefinition::class)->whereNull('owner_user_id');
    }

    public function spells(): HasMany
    {
        return $this->hasMany(SpellDefinition::class)->whereNull('owner_user_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(SkillDefinition::class)->whereNull('owner_user_id');
    }
}
