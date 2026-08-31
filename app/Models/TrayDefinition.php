<?php

namespace App\Models;

use App\Models\Concerns\HasDefinitionScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrayDefinition extends Model
{
    use HasDefinitionScope;
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_collapsible' => 'boolean', 'default_open' => 'boolean', 'configuration' => 'array'];
    }

    public function gameSystemVersion(): BelongsTo
    {
        return $this->belongsTo(GameSystemVersion::class);
    }
}
