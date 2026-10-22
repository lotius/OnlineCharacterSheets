<?php

namespace Tests\Feature\GameSessions;

use App\Actions\GameSessions\CreateGameSession;
use App\Models\Character;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnControlTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private GameSession $game;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
        $this->owner = User::factory()->create();
        $this->game = app(CreateGameSession::class)->handle($this->owner, GameSystemVersion::query()->sole(), ['name' => 'Turns']);
        $this->ids = [];
        foreach ([2, 0, 1] as $order) {
            $character = Character::query()->create(['game_system_version_id' => $this->game->game_system_version_id, 'name' => "Hero {$order}"]);
            $this->game->sessionCharacters()->create(['character_id' => $character->id, 'turn_order' => $order]);
            $this->ids[$order] = $character->id;
        }
    }

    public function test_turns_follow_configured_order_wrap_and_record_atomic_revisions(): void
    {
        foreach (['next' => 0, 'previous' => 2, 'first' => 0] as $direction => $expected) {
            $this->actingAs($this->owner)->patch("/games/{$this->game->id}/turn", ['direction' => $direction])->assertRedirect();
            $this->assertSame($this->ids[$expected], $this->game->refresh()->current_character_id);
        }
        $this->assertSame(4, $this->game->current_revision);
        $this->assertSame([1, 2, 3, 4], GameEvent::query()->orderBy('revision')->pluck('revision')->all());
        $this->actingAs($this->owner)->patch("/games/{$this->game->id}/turn", ['direction' => 'select', 'character_id' => $this->ids[0]])->assertRedirect();
        $this->assertDatabaseCount('game_events', 4);
    }

    public function test_only_active_dms_can_change_or_undo_turns(): void
    {
        $user = User::factory()->create();
        $this->game->members()->create(['user_id' => $user->id, 'role' => 'player', 'status' => 'active']);
        $this->actingAs($user)->patch("/games/{$this->game->id}/turn", ['direction' => 'next'])->assertForbidden();
        $this->actingAs($this->owner)->patch("/games/{$this->game->id}/turn", ['direction' => 'next']);
        $event = GameEvent::query()->where('event_type', 'turn.changed')->sole();
        $this->actingAs($user)->post("/games/{$this->game->id}/events/{$event->id}/undo")->assertForbidden();
        $this->game->members()->where('user_id', $user->id)->update(['role' => 'co_dm']);
        $this->actingAs($user)->patch("/games/{$this->game->id}/turn", ['direction' => 'next'])->assertRedirect();
        $this->game->members()->where('user_id', $user->id)->update(['status' => 'removed']);
        $this->actingAs($user)->patch("/games/{$this->game->id}/turn", ['direction' => 'next'])->assertForbidden();
    }

    public function test_undo_appends_compensation_and_rejects_repeated_and_stale_undo(): void
    {
        $url = "/games/{$this->game->id}";
        $this->actingAs($this->owner)->patch("{$url}/turn", ['direction' => 'next']);
        $first = GameEvent::query()->where('event_type', 'turn.changed')->sole();
        $this->patch("{$url}/turn", ['direction' => 'next']);
        $second = GameEvent::query()->latest('id')->first();
        $this->post("{$url}/events/{$first->id}/undo")->assertSessionHasErrors('turn');
        $this->post("{$url}/events/{$second->id}/undo")->assertRedirect();
        $this->assertSame($this->ids[0], $this->game->refresh()->current_character_id);
        $this->assertSame(4, $this->game->current_revision);
        $this->assertDatabaseHas('game_events', ['event_type' => 'event.undone', 'undoes_event_id' => $second->id]);
        $this->assertSame('turn.changed', $second->refresh()->event_type);
        $this->post("{$url}/events/{$second->id}/undo")->assertSessionHasErrors('turn');
        $this->assertDatabaseCount('game_events', 4);
    }

    public function test_invalid_empty_and_cross_room_targets_do_not_mutate_state(): void
    {
        $url = "/games/{$this->game->id}/turn";
        $this->actingAs($this->owner)->patch($url, ['direction' => 'select', 'character_id' => 999999])->assertSessionHasErrors('turn');
        $this->patch($url, ['direction' => 'select'])->assertSessionHasErrors('character_id');
        $this->game->sessionCharacters()->update(['is_active' => false]);
        $this->patch($url, ['direction' => 'select', 'character_id' => $this->ids[0]])->assertSessionHasErrors('turn');
        $this->patch($url, ['direction' => 'next'])->assertSessionHasErrors('turn');
        $this->assertDatabaseCount('game_events', 1);
        $this->assertSame(1, $this->game->refresh()->current_revision);
    }

    public function test_first_turn_can_be_undone_to_not_started_and_foreign_events_are_rejected(): void
    {
        $other = app(CreateGameSession::class)->handle($this->owner, GameSystemVersion::query()->sole(), ['name' => 'Other room']);
        $foreign = Character::query()->create(['game_system_version_id' => $other->game_system_version_id, 'name' => 'Foreign hero']);
        $other->sessionCharacters()->create(['character_id' => $foreign->id]);
        $this->actingAs($this->owner)->patch("/games/{$this->game->id}/turn", ['direction' => 'select', 'character_id' => $foreign->id])->assertSessionHasErrors('turn');
        $this->patch("/games/{$other->id}/turn", ['direction' => 'next']);
        $foreignEvent = GameEvent::query()->where('event_type', 'turn.changed')->sole();
        $this->post("/games/{$this->game->id}/events/{$foreignEvent->id}/undo")->assertNotFound();
        $this->patch("/games/{$this->game->id}/turn", ['direction' => 'next']);
        $event = GameEvent::query()->where('game_session_id', $this->game->id)->where('event_type', 'turn.changed')->sole();
        $this->post("/games/{$this->game->id}/events/{$event->id}/undo")->assertRedirect();
        $this->assertNull($this->game->refresh()->current_character_id);
        $this->assertSame(3, $this->game->current_revision);
    }

    public function test_public_state_has_current_turn_but_no_audit_history_and_removal_clears_turn(): void
    {
        $this->game->update(['visibility' => 'public_view']);
        $this->actingAs($this->owner)->patch("/games/{$this->game->id}/turn", ['direction' => 'next']);
        $this->app['auth']->forgetGuards();
        $this->get("/games/{$this->game->id}")->assertInertia(fn ($page) => $page
            ->where('game.current_character_id', $this->ids[0])->has('game.turn_events', 0));
        $link = $this->game->sessionCharacters()->where('character_id', $this->ids[0])->sole();
        $this->actingAs($this->owner)->delete("/games/{$this->game->id}/characters/{$link->id}")->assertRedirect();
        $this->assertNull($this->game->refresh()->current_character_id);
        $this->assertDatabaseHas('game_events', ['event_type' => 'turn.cleared']);
    }

    public function test_dm_can_reorder_exactly_the_active_party_and_traversal_uses_it(): void
    {
        $url = "/games/{$this->game->id}/turn/order";
        $this->actingAs($this->owner)->put($url, ['character_ids' => [$this->ids[2], $this->ids[0], $this->ids[1]]])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('game_events', ['event_type' => 'turn.order_changed']);
        $this->patch("/games/{$this->game->id}/turn", ['direction' => 'first']);
        $this->assertSame($this->ids[2], $this->game->refresh()->current_character_id);
        $this->patch("/games/{$this->game->id}/turn", ['direction' => 'next']);
        $this->assertSame($this->ids[0], $this->game->refresh()->current_character_id);
        $revision = $this->game->current_revision;
        $this->put($url, ['character_ids' => [$this->ids[0]]])->assertSessionHasErrors('character_ids');
        $this->put($url, ['character_ids' => [$this->ids[0], $this->ids[0], $this->ids[1]]])->assertSessionHasErrors('character_ids.0');
        $this->put($url, ['character_ids' => [$this->ids[0], $this->ids[1], 999999]])->assertSessionHasErrors('character_ids');
        $this->assertSame($revision, $this->game->refresh()->current_revision);
        $this->actingAs(User::factory()->create())->put($url, ['character_ids' => array_values($this->ids)])->assertForbidden();
    }
}
