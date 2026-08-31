<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CharacterItemSlot extends Model
{
    protected $guarded = [];

    public function characterItem(): BelongsTo
    {
        return $this->belongsTo(CharacterItem::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(SlotDefinition::class, 'slot_definition_id');
    }
}
