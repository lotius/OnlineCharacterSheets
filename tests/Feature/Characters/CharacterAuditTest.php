<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Events\RoomRealtimePinged;
use App\Models\Character;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\User;
use App\Services\CharacterMutation;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CharacterAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $dm;

    private User $player;

    private GameSession $game;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([RoomRealtimePinged::class]);
        $this->seed(HeroQuestGameSystemSeeder::class);
        $this->dm = User::factory()->create();
        $this->player = User::factory()->create();
        $this->game = app(CreateGameSession::class)->handle($this->dm, GameSystemVersion::query()->sole(), ['name' => 'Audit']);
        $this->game->members()->create(['user_id' => $this->player->id, 'role' => 'player', 'status' => 'active']);
        $this->character = app(CreateCharacter::class)->handle($this->dm, $this->game, ['name' => 'Hero']);
        $this->game->sessionCharacters()->update(['assigned_user_id' => $this->player->id]);
        $this->actingAs($this->player);
        Event::fake([RoomRealtimePinged::class]);
    }

    private function latest(): GameEvent
    {
        return GameEvent::query()->where('game_session_id', $this->game->id)->latest('id')->firstOrFail();
    }

    private function undo(GameEvent $event): TestResponse
    {
        return $this->actingAs($this->dm)->post("/games/{$this->game->id}/events/{$event->id}/undo");
    }

    public function test_stat_change_has_one_revision_and_undo_restores_value_without_erasing_history(): void
    {
        $stat = $this->character->stats()->firstOrFail();
        $before = $stat->value;
        $this->patch("/characters/{$this->character->id}/stats/{$stat->id}", ['value' => 1])->assertRedirect();
        $event = $this->latest();
        $this->assertSame('stat.changed', $event->event_type);
        $this->assertSame($this->player->id, $event->actor_user_id);
        $this->assertSame($before, $event->before_state['value']);
        $this->assertSame(3, $this->game->refresh()->current_revision);
        $this->post("/games/{$this->game->id}/events/{$event->id}/undo")->assertForbidden();
        $this->undo($event)->assertRedirect();
        $this->assertSame($before, $stat->refresh()->value);
        $this->assertSame(4, $this->game->refresh()->current_revision);
        $this->assertDatabaseCount('game_events', 4);
        $this->undo($event)->assertSessionHasErrors('event');
    }

    public function test_no_op_is_not_recorded_and_aba_changes_cannot_be_undone(): void
    {
        $url = "/characters/{$this->character->id}/gold";
        $this->patch($url, ['gold' => 10]);
        $original = $this->latest();
        $this->patch($url, ['gold' => 10]);
        $this->assertDatabaseCount('game_events', 3);
        $this->patch($url, ['gold' => 20]);
        $this->patch($url, ['gold' => 10]);
        $this->undo($original)->assertSessionHasErrors('event');
        $this->assertSame(10, $this->character->refresh()->gold);
    }

    public function test_inventory_removal_and_equipment_restore_together_and_respect_capacity(): void
    {
        $url = "/characters/{$this->character->id}/items";
        $this->post($url, ['custom_name' => 'Shield', 'quantity' => 1])->assertRedirect();
        $item = $this->character->items()->sole();
        $slot = SlotDefinition::query()->where('key', 'left_hand')->sole();
        $this->put("{$url}/{$item->id}/slots/{$slot->id}")->assertRedirect();
        $this->delete("{$url}/{$item->id}")->assertRedirect();
        $removed = $this->latest();
        $this->undo($removed)->assertRedirect();
        $this->assertDatabaseHas('character_items', ['id' => $item->id, 'custom_name' => 'Shield']);
        $this->assertDatabaseHas('character_item_slots', ['character_item_id' => $item->id, 'slot_definition_id' => $slot->id]);
        $this->delete("{$url}/{$item->id}");
        $removedAgain = $this->latest();
        $this->post($url, ['custom_name' => 'Replacement', 'quantity' => 1]);
        $replacement = $this->character->items()->sole();
        $this->put("{$url}/{$replacement->id}/slots/{$slot->id}");
        $this->undo($removedAgain)->assertSessionHasErrors('event');
        $this->assertDatabaseMissing('character_items', ['id' => $item->id]);
    }

    public function test_ability_changes_and_private_notes_are_undoable_but_not_exposed_in_history(): void
    {
        $spell = SpellDefinition::query()->firstOrFail();
        $url = "/characters/{$this->character->id}/spells";
        $this->post($url, ['spell_definition_id' => $spell->id]);
        $owned = $this->character->spells()->sole();
        $this->post($url, ['spell_definition_id' => $spell->id]);
        $this->assertDatabaseCount('game_events', 3);
        $this->patch("{$url}/{$owned->id}", ['is_used' => true, 'charges' => 0]);
        $this->undo($this->latest())->assertRedirect();
        $this->assertFalse($owned->refresh()->is_used);
        $this->assertNull($owned->charges);
        $this->put("/characters/{$this->character->id}/notes", ['content' => 'Private secret', 'is_private' => true]);
        $noteEvent = $this->latest();
        $this->get("/games/{$this->game->id}/events")->assertOk()->assertDontSee('Private secret');
        $this->actingAs($this->player)->get("/games/{$this->game->id}/events")->assertForbidden();
        $this->undo($noteEvent)->assertRedirect();
        $this->assertDatabaseCount('character_notes', 0);
    }

    public function test_shared_character_mutations_and_compensations_reach_every_active_room(): void
    {
        $other = app(CreateGameSession::class)->handle($this->dm, GameSystemVersion::query()->sole(), ['name' => 'Shared']);
        $other->sessionCharacters()->create(['character_id' => $this->character->id]);
        $this->patch("/characters/{$this->character->id}/gold", ['gold' => 40]);
        $this->assertDatabaseCount('game_events', 5);
        $events = GameEvent::query()->where('event_type', 'gold.changed')->get();
        $this->assertSame($events[0]->mutation_id, $events[1]->mutation_id);
        $this->undo($events[0])->assertRedirect();
        $this->assertDatabaseCount('game_events', 7);
        $this->assertSame(0, $this->character->refresh()->gold);
        $this->actingAs($this->dm)->post("/games/{$other->id}/events/{$events[1]->id}/undo")->assertSessionHasErrors('event');
    }

    public function test_transaction_failure_rolls_back_state_revision_events_and_broadcast(): void
    {
        try {
            DB::transaction(function () {
                app(CharacterMutation::class)->run($this->character, 'gold.changed', 'gold', $this->character->id, fn () => $this->character->update(['gold' => 90]));
                throw new \RuntimeException('abort outer transaction');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('abort outer transaction', $exception->getMessage());
        }
        $this->assertSame(0, $this->character->refresh()->gold);
        $this->assertSame(2, $this->game->refresh()->current_revision);
        $this->assertDatabaseCount('game_events', 2);
        Event::assertNotDispatched(RoomRealtimePinged::class);
    }

    public function test_inactive_membership_or_character_never_grants_mutation_access(): void
    {
        $this->game->members()->where('user_id', $this->player->id)->update(['status' => 'removed']);
        $this->patch("/characters/{$this->character->id}/gold", ['gold' => 99])->assertForbidden();
        $this->game->members()->where('user_id', $this->player->id)->update(['status' => 'active']);
        $this->game->sessionCharacters()->update(['is_active' => false]);
        $this->patch("/characters/{$this->character->id}/gold", ['gold' => 99])->assertForbidden();
        $this->assertDatabaseCount('game_events', 2);
    }

    public function test_changed_stat_limits_block_undo_without_partial_changes(): void
    {
        $stat = $this->character->stats()->whereHas('definition', fn ($query) => $query->where('key', 'body'))->sole();
        $this->patch("/characters/{$this->character->id}/stats/{$stat->id}", ['value' => 1]);
        $event = $this->latest();
        $stat->definition->update(['maximum_value' => 1]);
        $this->undo($event)->assertSessionHasErrors('event');
        $this->assertSame(1, $stat->refresh()->value);
        $this->assertDatabaseCount('game_events', 3);
    }
}
