<?php

namespace Tests\Feature\GameSessions;

use App\Actions\GameSessions\CreateGameSession;
use App\Events\PublicRoomDirectoryChanged;
use App\Events\RoomRealtimePinged;
use App\Events\RoomViewerCountChanged;
use App\Models\GameSession;
use App\Models\GameSessionViewer;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
        Event::fake([RoomRealtimePinged::class]);

        $response = $this->actingAs($owner)->post('/games', [
            'name' => 'The Trial',
            'game_system_version_id' => GameSystemVersion::query()->sole()->id,
            'visibility' => 'invite_only',
        ]);

        $game = GameSession::query()->sole();
        $response->assertRedirect("/games/{$game->id}");
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $game->join_code);
        $this->assertDatabaseHas('game_session_members', ['game_session_id' => $game->id, 'user_id' => $owner->id, 'role' => 'dm', 'status' => 'active']);
        Event::assertDispatched(RoomRealtimePinged::class, fn ($event) => $event->gameSession->is($game));
    }

    public function test_dm_can_remove_character_from_room_and_broadcast_changes(): void
    {
        [$owner, $game] = $this->room();
        $this->actingAs($owner)->post("/games/{$game->id}/characters", [
            'name' => 'The Wizard',
            'character_class_definition_id' => null,
        ]);
        $sessionCharacter = $game->sessionCharacters()->sole();
        Event::fake([RoomRealtimePinged::class]);

        $this->actingAs($owner)->delete("/games/{$game->id}/characters/{$sessionCharacter->id}")
            ->assertRedirect();

        $this->assertFalse($sessionCharacter->refresh()->is_active);
        $this->actingAs($owner)->get("/games/{$game->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->has('game.characters', 0));
        Event::assertDispatched(RoomRealtimePinged::class, fn ($event) => $event->gameSession->is($game));
    }

    public function test_authenticated_user_can_open_create_room_page(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/games/create')->assertOk();
    }

    public function test_owner_can_update_room_settings_and_broadcast_changes(): void
    {
        [$owner, $game] = $this->room();
        Event::fake([RoomRealtimePinged::class, PublicRoomDirectoryChanged::class]);

        $this->actingAs($owner)->patch("/games/{$game->id}", [
            'name' => 'The Trial Revised',
            'visibility' => 'public_view',
        ])->assertRedirect();

        $game->refresh();

        $this->assertSame('The Trial Revised', $game->name);
        $this->assertSame('public_view', $game->visibility);
        $this->assertSame('lobby', $game->status);
        $this->assertSame(2, $game->current_revision);
        Event::assertDispatched(RoomRealtimePinged::class, fn ($event) => $event->gameSession->is($game) && $event->includePublicChannel);
        Event::assertDispatched(PublicRoomDirectoryChanged::class);
    }

    public function test_non_owner_cannot_update_room_settings(): void
    {
        [, $game] = $this->room();
        $player = User::factory()->create();
        $game->members()->create(['user_id' => $player->id, 'role' => 'co_dm', 'status' => 'active']);

        $this->actingAs($player)->patch("/games/{$game->id}", [
            'name' => 'Nope',
            'visibility' => 'public_view',
        ])->assertForbidden();

        $this->assertSame('The Trial', $game->refresh()->name);
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

    public function test_room_viewer_heartbeat_counts_members_and_anonymous_spectators(): void
    {
        [$owner, $game] = $this->room();
        $game->update(['visibility' => 'public_view']);
        Event::fake([RoomViewerCountChanged::class]);

        $this->actingAs($owner)
            ->postJson("/games/{$game->id}/viewers")
            ->assertOk()
            ->assertJson(['viewer_count' => 1]);

        $this->assertDatabaseHas('game_session_viewers', [
            'game_session_id' => $game->id,
            'viewer_key' => "user:{$owner->id}",
        ]);

        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $this->postJson("/games/{$game->id}/viewers")
            ->assertOk()
            ->assertJson(['viewer_count' => 2]);

        $this->assertSame(2, GameSessionViewer::query()->where('game_session_id', $game->id)->count());
        Event::assertDispatched(RoomViewerCountChanged::class, fn ($event) => $event->viewerCount === 2);
    }

    /** @return array{User, User, GameSession} */
    private function roomWithOwner(): array
    {
        [$owner, $game] = $this->room();
        $player = User::factory()->create();

        return [$owner, $player, $game];
    }

    /** @return array{User, GameSession} */
    private function room(): array
    {
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), [
            'name' => 'The Trial',
            'visibility' => 'invite_only',
        ]);

        return [$owner, $game];
    }
}
