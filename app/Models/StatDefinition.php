<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatDefinition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_value' => 'json', 'default_max_value' => 'json', 'is_public' => 'boolean', 'configuration' => 'array'];
    }

    public function gameSystemVersion(): BelongsTo
    {
        return $this->belongsTo(GameSystemVersion::class);
    }
}
