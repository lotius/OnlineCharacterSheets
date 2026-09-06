<?php

namespace Tests\Feature\Foundation;

use App\Actions\GameSessions\CreateGameSession;
use App\Actions\GameSessions\RequestGameSessionJoin;
use App\Models\Character;
use App\Models\GameSystemVersion;
use App\Models\SessionCharacter;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class GameSessionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_heroquest_definitions_are_seeded_from_configuration(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);

        $version = GameSystemVersion::query()->with(['stats', 'slots', 'trays', 'characterClasses'])->sole();

        $this->assertSame(['body', 'mind'], $version->stats->sortBy('display_order')->pluck('key')->all());
        $this->assertCount(4, $version->slots);
        $this->assertCount(6, $version->trays);
        $this->assertCount(12, $version->characterClasses);
    }

    public function test_creating_a_room_makes_its_owner_an_active_dm(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();

        $session = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'Friday Night']);

        $this->assertSame($owner->id, $session->owner_user_id);
        $this->assertDatabaseHas('game_session_members', ['game_session_id' => $session->id, 'user_id' => $owner->id, 'role' => 'dm', 'status' => 'active']);
    }

    public function test_join_request_does_not_grant_room_access_until_approved(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $session = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'Friday Night']);

        app(RequestGameSessionJoin::class)->handle($player, $session);

        $this->assertFalse(Gate::forUser($player)->allows('view', $session));
        $this->assertTrue(Gate::forUser($owner)->allows('manageMembers', $session));
    }

    public function test_player_can_update_only_the_character_assigned_to_them(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $version = GameSystemVersion::query()->sole();
        $session = app(CreateGameSession::class)->handle($owner, $version, ['name' => 'Friday Night']);
        $session->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);
        $assigned = Character::query()->create(['game_system_version_id' => $version->id, 'name' => 'The Elf']);
        $other = Character::query()->create(['game_system_version_id' => $version->id, 'name' => 'The Dwarf']);
        SessionCharacter::query()->create(['game_session_id' => $session->id, 'character_id' => $assigned->id, 'assigned_user_id' => $player->id]);
        SessionCharacter::query()->create(['game_session_id' => $session->id, 'character_id' => $other->id]);

        $this->assertTrue(Gate::forUser($player)->allows('update', $assigned));
        $this->assertFalse(Gate::forUser($player)->allows('update', $other));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $other));
    }
}
