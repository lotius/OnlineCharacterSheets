<?php

namespace Tests\Feature\GameSessions;

use App\Actions\GameSessions\CreateGameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SheetLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_layout_overrides_system_default_and_can_be_reset(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'Layouts']);
        $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);

        $this->actingAs($owner)->get("/games/{$game->id}")->assertInertia(fn ($page) => $page
            ->where('system.layouts.sheet.2.title', 'Potions')
            ->where('game.sheet_layouts.room', null));

        $this->actingAs($player)->put("/games/{$game->id}/layouts", ['sheet' => [['type' => 'notes']]])->assertForbidden();
        $this->actingAs($owner)->put("/games/{$game->id}/layouts", ['sheet' => [['type' => 'bogus']]])->assertSessionHasErrors('sheet.0.type');
        $this->actingAs($owner)->put("/games/{$game->id}/layouts", ['sheet' => [['type' => 'notes', 'title' => 'Journal'], ['type' => 'stats', 'keys' => ['body']]]])->assertSessionHasNoErrors();

        $this->actingAs($player)->get("/games/{$game->id}")->assertInertia(fn ($page) => $page
            ->where('system.layouts.sheet.0.type', 'notes')
            ->where('system.layouts.sheet.0.title', 'Journal')
            ->where('system.layouts.sheet.1.keys', ['body'])
            ->where('system.layouts.card.0.type', 'stats'));
        $this->assertDatabaseHas('game_events', ['game_session_id' => $game->id, 'event_type' => 'session.layouts_changed']);

        $this->actingAs($owner)->put("/games/{$game->id}/layouts", ['sheet' => null, 'card' => null])->assertSessionHasNoErrors();
        $this->assertNull($game->refresh()->settings['layouts'] ?? null);
    }

    public function test_systems_without_layout_configuration_generate_one_from_sheet_sections(): void
    {
        $this->seed(HeroQuestGameSystemSeeder::class);
        $version = GameSystemVersion::query()->sole();
        $version->update(['configuration' => []]);
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, $version, ['name' => 'Generated']);

        $this->actingAs($owner)->get("/games/{$game->id}")->assertInertia(fn ($page) => $page
            ->where('system.layouts.sheet.0.type', 'stats')
            ->where('system.layouts.sheet.1.type', 'equipment')
            ->where('system.layouts.sheet.2.exclude_categories', ['artifacts'])
            ->where('system.layouts.sheet.5.category', 'artifacts'));
    }
}
