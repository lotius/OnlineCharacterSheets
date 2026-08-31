<?php

namespace App\Actions\GameSessions;

use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateGameSession
{
    /** @param array{name: string, visibility?: string, settings?: array<string, mixed>} $attributes */
    public function handle(User $owner, GameSystemVersion $version, array $attributes): GameSession
    {
        return DB::transaction(function () use ($owner, $version, $attributes): GameSession {
            $session = GameSession::query()->create([
                'game_system_version_id' => $version->id,
                'owner_user_id' => $owner->id,
                'name' => $attributes['name'],
                'join_code' => $this->uniqueJoinCode(),
                'visibility' => $attributes['visibility'] ?? 'invite_only',
                'settings' => $attributes['settings'] ?? [],
            ]);

            $session->members()->create([
                'user_id' => $owner->id,
                'role' => 'dm',
                'status' => 'active',
                'joined_at' => now(),
                'approved_at' => now(),
            ]);

            return $session;
        });
    }

    private function uniqueJoinCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (GameSession::query()->where('join_code', $code)->exists());

        return $code;
    }
}
