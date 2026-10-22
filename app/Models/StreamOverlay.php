<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StreamOverlay extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'token_encrypted' => 'encrypted', 'last_used_at' => 'datetime'];
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Issue a fresh browser-source token; the previous URL and channel stop working immediately. */
    public function rotateToken(): string
    {
        $token = Str::random(48);
        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'token_encrypted' => $token,
            'channel_key' => Str::random(40),
        ]);

        return $token;
    }

    public function url(): string
    {
        return url('/overlay/'.$this->token_encrypted);
    }

    public static function findByToken(string $token): ?self
    {
        return strlen($token) === 48 ? self::query()->where('token_hash', self::hashToken($token))->first() : null;
    }
}
