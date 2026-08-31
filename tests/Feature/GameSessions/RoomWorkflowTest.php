<?php

namespace Tests\Feature\GameSessions;

use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_authenticated_user_can_create_a_room(): void
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post('/games', [
            'name' => 'The Trial',
            'game_system_version_id' => GameSystemVersion::query()->sole()->id,
            'visibility' => 'invite_only',
        ]);

        $game = GameSession::query()->sole();
        $response->assertRedirect("/games/{$game->id}");
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $game->join_code);
        $this->assertDatabaseHas('game_session_members', ['game_session_id' => $game->id, 'user_id' => $owner->id, 'role' => 'dm', 'status' => 'active']);
    }

    public function test_player_can_request_to_join_by_code_and_dm_can_approve(): void
    {
        [$owner, $player, $game] = $this->roomWithOwner();

        $this->actingAs($player)->post('/games/join', ['join_code' => strtolower($game->join_code)])
            ->assertRedirect('/games');
        $membership = $game->members()->where('user_id', $player->id)->sole();
        $this->assertSame('requested', $membership->status);
        $this->actingAs($player)->get("/games/{$game->id}")->assertForbidden();

        $this->actingAs($owner)->patch("/games/{$game->id}/members/{$membership->id}/approve")
            ->assertRedirect();
        $this->assertSame('active', $membership->refresh()->status);
        $this->actingAs($player)->get("/games/{$game->id}")->assertOk();
    }

    public function test_non_dm_cannot_manage_members(): void
    {
        [$owner, $player, $game] = $this->roomWithOwner();
        $outsider = User::factory()->create();
        $this->actingAs($player)->post('/games/join', ['join_code' => $game->join_code]);
        $membership = $game->members()->where('user_id', $player->id)->sole();

        $this->actingAs($outsider)->patch("/games/{$game->id}/members/{$membership->id}/approve")
            ->assertForbidden();
        $this->assertSame('requested', $membership->refresh()->status);
        $this->assertTrue($owner->exists);
    }

    public function test_dm_can_reject_request_and_player_can_request_again(): void
    {
        [$owner, $player, $game] = $this->roomWithOwner();
        $this->actingAs($player)->post('/games/join', ['join_code' => $game->join_code]);
        $membership = $game->members()->where('user_id', $player->id)->sole();

        $this->actingAs($owner)->patch("/games/{$game->id}/members/{$membership->id}/reject");
        $this->assertSame('rejected', $membership->refresh()->status);
        $this->actingAs($player)->post('/games/join', ['join_code' => $game->join_code]);
        $this->assertSame('requested', $membership->refresh()->status);
    }

    public function test_dm_can_remove_an_active_player(): void
    {
        [$owner, $player, $game] = $this->roomWithOwner();
        $membership = $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);

        $this->actingAs($owner)->delete("/games/{$game->id}/members/{$membership->id}");

        $this->assertSame('removed', $membership->refresh()->status);
        $this->actingAs($player)->get("/games/{$game->id}")->assertForbidden();
    }

    /** @return array{User, User, GameSession} */
    private function roomWithOwner(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $this->actingAs($owner)->post('/games', [
            'name' => 'The Trial',
            'game_system_version_id' => GameSystemVersion::query()->sole()->id,
            'visibility' => 'invite_only',
        ]);

        return [$owner, $player, GameSession::query()->sole()];
    }
}
