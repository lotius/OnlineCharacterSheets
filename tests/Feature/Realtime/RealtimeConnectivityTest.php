<?php

namespace Tests\Feature\Realtime;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Events\RoomRealtimePinged;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeConnectivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_active_member_can_authorize_private_room_channel(): void
    {
        [$owner, $game] = $this->room();
        $player = User::factory()->create();
        $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);
        $this->assertTrue($game->members()->where('user_id', $player->id)->where('status', 'active')->exists());

        $this->actingAs($player)->post('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-games.{$game->id}",
        ])->assertOk();

        $this->assertTrue($owner->exists);
    }

    public function test_pending_member_and_outsider_cannot_authorize_private_room_channel(): void
    {
        [, $game] = $this->room();
        $pending = User::factory()->create();
        $outsider = User::factory()->create();
        $game->members()->create(['user_id' => $pending->id, 'role' => 'player', 'status' => 'requested']);

        foreach ([$pending, $outsider] as $user) {
            $this->actingAs($user)->post('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-games.{$game->id}",
            ])->assertForbidden();
        }
    }

    public function test_ping_endpoint_requires_room_access_and_dispatches_event(): void
    {
        [$owner, $game] = $this->room();
        $outsider = User::factory()->create();
        Event::fake([RoomRealtimePinged::class]);

        $this->actingAs($outsider)->post("/games/{$game->id}/realtime/ping")->assertForbidden();
        $this->actingAs($owner)->post("/games/{$game->id}/realtime/ping")->assertRedirect();
        Event::assertDispatched(RoomRealtimePinged::class, fn ($event) => $event->gameSession->is($game));
    }

    public function test_a_websocket_outage_never_fails_an_already_saved_change(): void
    {
        [$owner, $game] = $this->room();
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'Hero']);
        $body = $character->stats()->whereHas('definition', fn ($query) => $query->where('key', 'body'))->sole();
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 9, // nothing listens here
            'broadcasting.connections.reverb.options.scheme' => 'http',
        ]);
        app('Illuminate\Broadcasting\BroadcastManager')->purge('reverb');

        $this->actingAs($owner)->patchJson("/characters/{$character->id}/stats/{$body->id}", ['delta' => -1])->assertOk();
        $this->assertSame(5, $body->refresh()->value);
        $this->patch("/games/{$game->id}/turn", ['direction' => 'next'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($character->id, $game->refresh()->current_character_id);
    }

    public function test_public_rooms_also_emit_safe_invalidation_on_public_channel(): void
    {
        [, $privateGame] = $this->room();
        $publicGame = $privateGame->replicate()->fill(['name' => 'Public Trial', 'join_code' => 'PUBLIC01', 'visibility' => 'public_view']);
        $publicGame->save();

        $privateChannels = (new RoomRealtimePinged($privateGame))->broadcastOn();
        $publicChannels = (new RoomRealtimePinged($publicGame))->broadcastOn();

        $this->assertCount(1, $privateChannels);
        $this->assertInstanceOf(PrivateChannel::class, $privateChannels[0]);
        $this->assertCount(2, $publicChannels);
        $this->assertInstanceOf(Channel::class, $publicChannels[1]);
        $this->assertSame("games.public.{$publicGame->id}", $publicChannels[1]->name);
        $this->assertSame(['game_id', 'room_name', 'revision', 'visibility', 'character_id', 'character_revision', 'sent_at'], array_keys((new RoomRealtimePinged($publicGame))->broadcastWith()));
        $this->assertSame('Public Trial', (new RoomRealtimePinged($publicGame))->broadcastWith()['room_name']);
        $this->assertSame('public_view', (new RoomRealtimePinged($publicGame))->broadcastWith()['visibility']);
    }

    /** @return array{User, GameSession} */
    private function room(): array
    {
        $owner = User::factory()->create();
        $game = app(CreateGameSession::class)->handle($owner, GameSystemVersion::query()->sole(), ['name' => 'The Trial']);

        return [$owner, $game];
    }
}
