<?php

namespace Tests\Feature\Realtime;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomResyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_authoritative_state_and_revision_reflect_mutations_and_filter_public_data(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'Live', 'visibility' => 'public_view']);
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'Hero']);
        $character->note()->create(['content' => 'Secret', 'is_private' => true]);
        $this->getJson("/games/{$game->id}/state")->assertOk()
            ->assertJsonPath('game.current_revision', 2)->assertJsonPath('game.join_code', null)
            ->assertJsonPath('game.members', [])->assertJsonPath('game.players', [])
            ->assertJsonPath('game.game_events', [])->assertJsonPath('game.characters.0.note', null)
            ->assertDontSee('Secret');
        $this->getJson("/games/{$game->id}/revision")->assertExactJson(['game_id' => $game->id, 'revision' => 2, 'is_spectator' => true]);
        $gold = $character->stats()->whereHas('definition', fn ($query) => $query->where('key', 'gold'))->sole();
        $this->actingAs($owner)->patch("/characters/{$character->id}/stats/{$gold->id}", ['delta' => 25])->assertRedirect();
        $this->getJson("/games/{$game->id}/characters/{$character->id}/state")->assertOk()->assertJsonPath('game.current_revision', 3)
            ->assertJsonPath('character.revision', 1)
            ->assertJsonPath('character.stats.5.key', 'gold')->assertJsonPath('character.stats.5.value', 25);
        $this->getJson("/games/{$game->id}/state")->assertOk()->assertJsonPath('game.current_revision', 3)->assertJsonCount(3, 'game.game_events');
    }

    public function test_revoked_access_and_removed_character_are_rejected_on_resync(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'Private']);
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'Hero']);
        $member = $game->members()->create(['user_id' => $viewer->id, 'role' => 'observer', 'status' => 'active']);
        $this->actingAs($viewer)->getJson("/games/{$game->id}/revision")->assertOk();
        $member->update(['status' => 'removed']);
        $this->getJson("/games/{$game->id}/revision")->assertForbidden();
        $this->getJson("/games/{$game->id}/state")->assertForbidden();
        $this->getJson("/games/{$game->id}/characters/{$character->id}/state")->assertForbidden();
        $game->sessionCharacters()->update(['is_active' => false]);
        $this->actingAs($owner)->getJson("/games/{$game->id}/revision?character_id={$character->id}")->assertNotFound();
        $this->getJson("/games/{$game->id}/characters/{$character->id}/state")->assertNotFound();
    }
}
