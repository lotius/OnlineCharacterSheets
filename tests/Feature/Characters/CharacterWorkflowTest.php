<?php

namespace Tests\Feature\Characters;

use App\Actions\GameSessions\CreateGameSession;
use App\Models\Character;
use App\Models\CharacterClassDefinition;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\SessionCharacter;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_dm_can_create_character_with_class_configured_stats(): void
    {
        [$owner, $game] = $this->room();
        $barbarian = CharacterClassDefinition::query()->where('key', 'barbarian')->sole();

        $this->actingAs($owner)->post("/games/{$game->id}/characters", [
            'name' => 'Rogar', 'character_class_definition_id' => $barbarian->id,
        ])->assertRedirect();

        $character = Character::query()->with('stats.definition')->sole();
        $this->assertSame(['body' => 8, 'mind' => 2], $character->stats->mapWithKeys(fn ($stat) => [$stat->definition->key => $stat->value])->all());
        $this->assertDatabaseHas('session_characters', ['game_session_id' => $game->id, 'character_id' => $character->id]);
    }

    public function test_only_dm_can_create_and_assign_characters(): void
    {
        [$owner, $game] = $this->room();
        $player = User::factory()->create();
        $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);
        $character = Character::query()->create(['game_system_version_id' => $game->game_system_version_id, 'owner_user_id' => $owner->id, 'name' => 'Rogar']);
        $sessionCharacter = SessionCharacter::query()->create(['game_session_id' => $game->id, 'character_id' => $character->id]);

        $this->actingAs($player)->post("/games/{$game->id}/characters", ['name' => 'Unauthorized'])->assertForbidden();
        $this->actingAs($player)->patch("/games/{$game->id}/characters/{$sessionCharacter->id}/assignment", ['assigned_user_id' => $player->id])->assertForbidden();
        $this->actingAs($owner)->patch("/games/{$game->id}/characters/{$sessionCharacter->id}/assignment", ['assigned_user_id' => $player->id])->assertRedirect();
        $this->assertSame($player->id, $sessionCharacter->refresh()->assigned_user_id);
    }

    public function test_character_cannot_be_assigned_to_inactive_user(): void
    {
        [$owner, $game] = $this->room();
        $outsider = User::factory()->create();
        $character = Character::query()->create(['game_system_version_id' => $game->game_system_version_id, 'owner_user_id' => $owner->id, 'name' => 'Rogar']);
        $sessionCharacter = SessionCharacter::query()->create(['game_session_id' => $game->id, 'character_id' => $character->id]);

        $this->actingAs($owner)->patch("/games/{$game->id}/characters/{$sessionCharacter->id}/assignment", ['assigned_user_id' => $outsider->id])
            ->assertSessionHasErrors('assigned_user_id');
        $this->assertNull($sessionCharacter->refresh()->assigned_user_id);
    }

    public function test_assigned_player_and_dm_can_update_stats_but_other_player_cannot(): void
    {
        [$owner, $game] = $this->room();
        $assigned = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$assigned, $other] as $player) {
            $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);
        }
        $class = CharacterClassDefinition::query()->where('key', 'wizard')->sole();
        $this->actingAs($owner)->post("/games/{$game->id}/characters", ['name' => 'Mentor', 'character_class_definition_id' => $class->id]);
        $character = Character::query()->with('stats.definition')->sole();
        $sessionCharacter = SessionCharacter::query()->sole();
        $this->actingAs($owner)->patch("/games/{$game->id}/characters/{$sessionCharacter->id}/assignment", ['assigned_user_id' => $assigned->id]);
        $body = $character->stats->first(fn ($stat) => $stat->definition->key === 'body');

        $this->actingAs($assigned)->patch("/characters/{$character->id}/stats/{$body->id}", ['value' => 3])->assertRedirect();
        $this->assertSame(3, $body->refresh()->value);
        $this->actingAs($other)->patch("/characters/{$character->id}/stats/{$body->id}", ['value' => 2])->assertForbidden();
        $this->actingAs($other)->get("/games/{$game->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('game.characters.0.can_update', false));
        $this->actingAs($assigned)->get("/games/{$game->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('game.characters.0.can_update', true));
        $this->actingAs($owner)->patch("/characters/{$character->id}/stats/{$body->id}", ['value' => 2])->assertRedirect();
        $this->assertSame(2, $body->refresh()->value);
    }

    public function test_stat_values_are_bounded_by_configured_minimum_and_character_maximum(): void
    {
        [$owner, $game] = $this->room();
        $class = CharacterClassDefinition::query()->where('key', 'wizard')->sole();
        $this->actingAs($owner)->post("/games/{$game->id}/characters", ['name' => 'Mentor', 'character_class_definition_id' => $class->id]);
        $character = Character::query()->with('stats.definition')->sole();
        $body = $character->stats->first(fn ($stat) => $stat->definition->key === 'body');

        $this->actingAs($owner)->patch("/characters/{$character->id}/stats/{$body->id}", ['value' => -1])->assertSessionHasErrors('value');
        $this->actingAs($owner)->patch("/characters/{$character->id}/stats/{$body->id}", ['value' => 5])->assertSessionHasErrors('value');
        $this->assertSame(4, $body->refresh()->value);
    }

    /** @return array{User, GameSession} */
    private function room(): array
    {
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'The Trial']);

        return [$owner, $game];
    }
}
