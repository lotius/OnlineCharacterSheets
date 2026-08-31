<?php

namespace App\Models;

use App\Models\Concerns\HasDefinitionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellDefinition extends Model
{
    use HasDefinitionScope;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }

    public function gameSystemVersion(): BelongsTo
    {
        return $this->belongsTo(GameSystemVersion::class);
    }
}
