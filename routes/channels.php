<?php

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('games.{game}', function (User $user, string $game): bool {
    return GameSession::query()
        ->whereKey($game)
        ->where(function ($query) use ($user) {
            $query->where('owner_user_id', $user->id)
                ->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)->where('status', 'active'));
        })
        ->exists();
});
