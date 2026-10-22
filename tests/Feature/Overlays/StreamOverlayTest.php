<?php

namespace Tests\Feature\Overlays;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Events\RoomRealtimePinged;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\StreamOverlay;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StreamOverlayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private GameSession $game;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomRealtimePinged::class]);
        $this->seed(HeroQuestGameSystemSeeder::class);
        $this->owner = User::factory()->create();
        $this->game = app(CreateGameSession::class)->handle($this->owner, GameSystemVersion::query()->sole(), ['name' => 'Stream night']);
        $character = app(CreateCharacter::class)->handle($this->owner, $this->game, ['name' => 'Rogar']);
        $character->note()->create(['content' => 'Secret plan', 'is_private' => true]);
    }

    private function overlay(): StreamOverlay
    {
        $this->actingAs($this->owner)->post("/games/{$this->game->id}/overlays", ['name' => 'Main', 'preset' => 'hero-column'])->assertRedirect();

        return StreamOverlay::query()->sole();
    }

    public function test_dm_creates_overlay_and_token_url_renders_filtered_read_only_state_without_login(): void
    {
        $overlay = $this->overlay();
        $token = $overlay->token_encrypted;
        $this->assertNotSame($token, $overlay->getRawOriginal('token_hash'));
        $this->assertCount(35, $overlay->configuration['elements']);

        auth()->logout();
        $this->getJson("/overlay/{$token}/state")->assertOk()
            ->assertJsonPath('characters.0.name', 'Rogar')
            ->assertJsonPath('characters.0.note', null)
            ->assertJsonPath('characters.0.can_update', false)
            ->assertJsonPath('overlay.channel', "overlays.{$overlay->channel_key}")
            ->assertJsonMissingPath('game.join_code')
            ->assertDontSee('Secret plan');
        $this->get("/overlay/{$token}")->assertOk()->assertInertia(fn ($page) => $page->component('Overlays/Show')->where('token', $token));
        $this->assertGuest();
    }

    public function test_rotating_the_token_revokes_the_old_url_and_channel(): void
    {
        $overlay = $this->overlay();
        [$oldToken, $oldChannel] = [$overlay->token_encrypted, $overlay->channel_key];
        $this->post("/games/{$this->game->id}/overlays/{$overlay->id}/token")->assertRedirect();
        $overlay->refresh();

        $this->getJson("/overlay/{$oldToken}/state")->assertNotFound();
        $this->getJson("/overlay/{$overlay->token_encrypted}/state")->assertOk();
        $this->assertNotSame($oldChannel, $overlay->channel_key);
        $channels = collect((new RoomRealtimePinged($this->game))->broadcastOn())->pluck('name');
        $this->assertContains("overlays.{$overlay->channel_key}", $channels);
        $this->assertNotContains("overlays.{$oldChannel}", $channels);
    }

    public function test_only_room_managers_can_manage_overlays_and_tokens_cannot_mutate(): void
    {
        $overlay = $this->overlay();
        $player = User::factory()->create();
        $this->game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);

        $this->actingAs($player)->post("/games/{$this->game->id}/overlays", ['name' => 'Mine', 'preset' => 'blank'])->assertForbidden();
        $this->actingAs($player)->get("/games/{$this->game->id}/overlays/{$overlay->id}/edit")->assertForbidden();
        $this->actingAs($player)->post("/games/{$this->game->id}/overlays/{$overlay->id}/token")->assertForbidden();
        auth()->logout();
        $this->postJson("/overlay/{$overlay->token_encrypted}/state")->assertStatus(405);
    }

    public function test_saved_layouts_are_validated_and_reject_unsafe_content(): void
    {
        $overlay = $this->overlay();
        $config = $overlay->configuration;
        $url = "/games/{$this->game->id}/overlays/{$overlay->id}";

        $bad = $config;
        $bad['elements'][0]['type'] = 'script';
        $this->put($url, ['name' => 'Main', 'configuration' => $bad])->assertSessionHasErrors('elements.0.type');

        $bad = $config;
        $bad['elements'][0]['props']['src'] = 'javascript:alert(1)';
        $this->put($url, ['name' => 'Main', 'configuration' => $bad])->assertSessionHasErrors('elements.0.props.src');

        $bad = $config;
        $bad['elements'][0]['style']['color'] = 'red; background:url(x)';
        $this->put($url, ['name' => 'Main', 'configuration' => $bad])->assertSessionHasErrors('elements.0.style.color');

        $good = $config;
        $good['elements'][0]['style']['injected'] = 'position:fixed';
        $good['elements'][0]['x'] = 50;
        $this->put($url, ['name' => 'Renamed', 'configuration' => $good])->assertSessionHasNoErrors();
        $overlay->refresh();
        $this->assertSame('Renamed', $overlay->name);
        $this->assertEquals(50, $overlay->configuration['elements'][0]['x']);
        $this->assertArrayNotHasKey('injected', $overlay->configuration['elements'][0]['style']);
    }
}
