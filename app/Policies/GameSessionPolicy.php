<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\User;

class GameSessionPolicy
{
    public function view(User $user, GameSession $session): bool
    {
        return $session->owner_user_id === $user->id || $session->members()->where('user_id', $user->id)->where('status', 'active')->exists();
    }

    public function update(User $user, GameSession $session): bool
    {
        return $session->owner_user_id === $user->id || $session->members()->where('user_id', $user->id)->whereIn('role', ['dm', 'co_dm'])->where('status', 'active')->exists();
    }

    public function manageMembers(User $user, GameSession $session): bool
    {
        return $this->update($user, $session);
    }
}
