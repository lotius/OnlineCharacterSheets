<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function gameSystemVersion(): BelongsTo
    {
        return $this->belongsTo(GameSystemVersion::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GameSessionMember::class);
    }

    public function sessionCharacters(): HasMany
    {
        return $this->hasMany(SessionCharacter::class);
    }

    public function viewers(): HasMany
    {
        return $this->hasMany(GameSessionViewer::class);
    }
}
