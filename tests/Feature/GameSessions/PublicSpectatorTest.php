<?php

namespace Tests\Feature\GameSessions;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSpectatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_guest_can_discover_and_view_a_public_room_and_character_sheet(): void
    {
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), [
            'name' => 'Open Table',
            'visibility' => 'public_view',
        ]);
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'The Wizard']);
        $character->note()->create(['content' => 'Hidden plan', 'is_private' => true]);

        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->has('publicRooms', 1)
            ->where('publicRooms.0.name', 'Open Table'));

        $this->get("/games/{$game->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('game.is_spectator', true)
            ->where('game.can_manage_members', false)
            ->where('game.members', [])
            ->where('game.characters.0.name', 'The Wizard')
            ->where('game.characters.0.can_update', false)
            ->where('game.characters.0.note', null)
            ->has('game.characters.0.trays'));

        $this->get("/games/{$game->id}/characters/{$character->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('character.name', 'The Wizard')
            ->where('character.can_update', false)
            ->where('character.note', null));
    }

    public function test_guest_cannot_view_a_non_public_room_or_character(): void
    {
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), [
            'name' => 'Private Table',
            'visibility' => 'invite_only',
        ]);
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'The Dwarf']);

        $this->get("/games/{$game->id}")->assertForbidden();
        $this->get("/games/{$game->id}/characters/{$character->id}")->assertForbidden();
    }
}
