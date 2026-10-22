<?php

namespace App\Policies;

use App\Models\Character;
use App\Models\User;

class CharacterPolicy
{
    public function view(User $user, Character $character): bool
    {
        return $character->owner_user_id === $user->id || $character->sessions()->whereHas('gameSession.members', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))->exists();
    }

    public function update(User $user, Character $character): bool
    {
        return $character->sessions()->where('is_active', true)->where(function ($query) use ($user) {
            $query->where(function ($assigned) use ($user) {
                $assigned->where('assigned_user_id', $user->id)->whereHas('gameSession.members', fn ($member) => $member->where('user_id', $user->id)->where('status', 'active'));
            })
                ->orWhereHas('gameSession', fn ($session) => $session->where('owner_user_id', $user->id)->orWhereHas('members', fn ($member) => $member->where('user_id', $user->id)->whereIn('role', ['dm', 'co_dm'])->where('status', 'active')));
        })->exists();
    }
}
