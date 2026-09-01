<?php

namespace App\Http\Controllers;

use App\Actions\GameSessions\ApproveGameSessionMember;
use App\Actions\GameSessions\BroadcastGameSessionUpdate;
use App\Actions\GameSessions\RejectGameSessionMember;
use App\Actions\GameSessions\RemoveGameSessionMember;
use App\Models\GameSession;
use App\Models\GameSessionMember;
use Illuminate\Http\RedirectResponse;

class GameSessionMemberController extends Controller
{
    public function approve(GameSession $game, GameSessionMember $member, ApproveGameSessionMember $approve, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('manageMembers', $game);
        $approve->handle($game, $member);
        $broadcast->room($game);

        return back()->with('success', 'Member approved.');
    }

    public function reject(GameSession $game, GameSessionMember $member, RejectGameSessionMember $reject, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('manageMembers', $game);
        $reject->handle($game, $member);
        $broadcast->room($game);

        return back()->with('success', 'Join request rejected.');
    }

    public function destroy(GameSession $game, GameSessionMember $member, RemoveGameSessionMember $remove, BroadcastGameSessionUpdate $broadcast): RedirectResponse
    {
        $this->authorize('manageMembers', $game);
        $remove->handle($game, $member);
        $broadcast->room($game);

        return back()->with('success', 'Member removed.');
    }
}
