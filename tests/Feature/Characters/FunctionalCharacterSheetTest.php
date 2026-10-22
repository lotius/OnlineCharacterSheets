<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Events\RoomRealtimePinged;
use App\Models\Character;
use App\Models\CharacterClassDefinition;
use App\Models\GameEvent;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FunctionalCharacterSheetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_assigned_player_can_manage_inventory_quantity_and_equipment(): void
    {
        [$owner, $player, $game, $character] = $this->assignedCharacter();
        $sword = ItemDefinition::query()->where('key', 'broadsword')->sole();
        $leftHand = SlotDefinition::query()->where('key', 'left_hand')->sole();

        $this->actingAs($player)->post("/characters/{$character->id}/items", ['item_definition_id' => $sword->id, 'quantity' => 1])->assertRedirect();
        $item = $character->items()->where('item_definition_id', $sword->id)->sole();
        $this->actingAs($player)->patch("/characters/{$character->id}/items/{$item->id}", ['quantity' => 3])->assertRedirect();
        $this->actingAs($player)->put("/characters/{$character->id}/items/{$item->id}/slots/{$leftHand->id}")->assertRedirect();

        $this->assertSame(3, $item->refresh()->quantity);
        $this->assertDatabaseHas('character_item_slots', ['character_item_id' => $item->id, 'slot_definition_id' => $leftHand->id]);
        $this->assertTrue($owner->exists && $game->exists);
    }

    public function test_using_a_consumable_removes_one_and_the_last_use_removes_the_stack(): void
    {
        Event::fake([RoomRealtimePinged::class]);
        [, $player, , $character] = $this->assignedCharacter();
        $potion = ItemDefinition::query()->where('key', 'healing_potion')->sole();
        $this->actingAs($player)->post("/characters/{$character->id}/items", ['item_definition_id' => $potion->id, 'quantity' => 1]);
        $this->actingAs($player)->post("/characters/{$character->id}/items", ['item_definition_id' => $potion->id, 'quantity' => 1]);
        $stack = $character->items()->where('item_definition_id', $potion->id)->sole();
        $this->assertSame(2, $stack->quantity, 'Consumables stack instead of creating a second row.');

        $this->actingAs($player)->postJson("/characters/{$character->id}/items/{$stack->id}/use")->assertOk()
            ->assertJsonPath('character.items.1.quantity', 1);
        $this->actingAs($player)->postJson("/characters/{$character->id}/items/{$stack->id}/use")->assertOk();
        $this->assertDatabaseMissing('character_items', ['id' => $stack->id]);
    }

    public function test_equipping_into_a_full_slot_swaps_the_previous_item_out(): void
    {
        [, $player, , $character] = $this->assignedCharacter();
        $leftHand = SlotDefinition::query()->where('key', 'left_hand')->sole();
        foreach (['broadsword', 'shield'] as $key) {
            $definition = ItemDefinition::query()->where('key', $key)->sole();
            $this->actingAs($player)->post("/characters/{$character->id}/items", ['item_definition_id' => $definition->id, 'quantity' => 1]);
        }
        [$sword, $shield] = $character->items()->whereHas('definition', fn ($query) => $query->whereIn('key', ['broadsword', 'shield']))->orderBy('id')->get()->all();

        $this->actingAs($player)->put("/characters/{$character->id}/items/{$sword->id}/slots/{$leftHand->id}")->assertSessionHasNoErrors();
        $this->actingAs($player)->put("/characters/{$character->id}/items/{$shield->id}/slots/{$leftHand->id}")->assertSessionHasNoErrors();
        $this->assertSame([$shield->id], DB::table('character_item_slots')->where('slot_definition_id', $leftHand->id)->pluck('character_item_id')->all());
        $this->assertSame(['item.unequipped', 'item.equipped'], GameEvent::query()->latest('id')->limit(2)->pluck('event_type')->reverse()->values()->all());
    }

    public function test_slot_rules_reject_items_in_the_wrong_slot(): void
    {
        [, $player, , $character] = $this->assignedCharacter();
        $helmet = ItemDefinition::query()->where('key', 'helmet')->sole();
        $this->actingAs($player)->post("/characters/{$character->id}/items", ['item_definition_id' => $helmet->id, 'quantity' => 1]);
        $item = $character->items()->where('item_definition_id', $helmet->id)->sole();
        $body = SlotDefinition::query()->where('key', 'body')->sole();

        $this->actingAs($player)->put("/characters/{$character->id}/items/{$item->id}/slots/{$body->id}")->assertSessionHasErrors('slot');
    }

    public function test_assigned_player_can_add_and_exhaust_spells_and_skills(): void
    {
        [, $player, , $character] = $this->assignedCharacter();
        $spell = SpellDefinition::query()->where('key', 'heal_body')->sole();
        $skill = SkillDefinition::query()->where('key', 'disarm_trap')->sole();

        $this->actingAs($player)->post("/characters/{$character->id}/spells", ['spell_definition_id' => $spell->id]);
        $this->actingAs($player)->post("/characters/{$character->id}/skills", ['skill_definition_id' => $skill->id]);
        $ownedSpell = $character->spells()->sole();
        $ownedSkill = $character->skills()->sole();
        $this->actingAs($player)->patch("/characters/{$character->id}/spells/{$ownedSpell->id}", ['is_used' => true, 'charges' => 0]);
        $this->actingAs($player)->patch("/characters/{$character->id}/skills/{$ownedSkill->id}", ['is_used' => true, 'charges' => 0]);

        $this->assertTrue($ownedSpell->refresh()->is_used);
        $this->assertTrue($ownedSkill->refresh()->is_used);
    }

    public function test_assigned_player_can_adjust_gold_with_deltas_clamped_at_zero(): void
    {
        [, $player, , $character] = $this->assignedCharacter();
        $gold = $character->stats()->whereHas('definition', fn ($query) => $query->where('key', 'gold'))->sole();

        $this->actingAs($player)->patchJson("/characters/{$character->id}/stats/{$gold->id}", ['delta' => 125])->assertOk()
            ->assertJsonPath('character.revision', 1);
        $this->actingAs($player)->patchJson("/characters/{$character->id}/stats/{$gold->id}", ['delta' => -200])->assertOk();
        $this->assertSame(0, $gold->refresh()->value);
        $this->actingAs($player)->patch("/characters/{$character->id}/stats/{$gold->id}", ['value' => -1])->assertSessionHasErrors('value');
    }

    public function test_private_notes_are_visible_to_controller_but_filtered_from_other_members(): void
    {
        [$owner, $player, $game, $character] = $this->assignedCharacter();
        $observer = User::factory()->create();
        $game->members()->create(['user_id' => $observer->id, 'role' => 'observer', 'status' => 'active']);

        $this->actingAs($player)->put("/characters/{$character->id}/notes", ['content' => 'Secret objective', 'is_private' => true]);
        $this->actingAs($player)->get("/games/{$game->id}/characters/{$character->id}")->assertOk()->assertInertia(fn ($page) => $page->where('character.note.content', 'Secret objective'));
        $this->actingAs($observer)->get("/games/{$game->id}/characters/{$character->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('character.can_update', false)
            ->where('character.note', null));
        $this->actingAs($owner)->get("/games/{$game->id}/characters/{$character->id}")->assertOk()->assertInertia(fn ($page) => $page
            ->where('character.can_update', true));
    }

    public function test_unassigned_or_outside_player_cannot_mutate_character_content(): void
    {
        [, , , $character] = $this->assignedCharacter();
        $outsider = User::factory()->create();
        $sword = ItemDefinition::query()->where('key', 'broadsword')->sole();

        $initialItems = $character->items()->count();
        $this->actingAs($outsider)->post("/characters/{$character->id}/items", ['item_definition_id' => $sword->id, 'quantity' => 1])->assertForbidden();
        $gold = $character->stats()->whereHas('definition', fn ($query) => $query->where('key', 'gold'))->sole();
        $this->actingAs($outsider)->patch("/characters/{$character->id}/stats/{$gold->id}", ['value' => 100])->assertForbidden();
        $this->assertDatabaseCount('character_items', $initialItems);
    }

    /** @return array{User, User, GameSession, Character} */
    private function assignedCharacter(): array
    {
        $owner = User::factory()->create();
        $player = User::factory()->create();
        $version = GameSystemVersion::query()->sole();
        $game = app(CreateGameSession::class)->handle($owner, $version, ['name' => 'The Trial']);
        $game->members()->create(['user_id' => $player->id, 'role' => 'player', 'status' => 'active']);
        $class = CharacterClassDefinition::query()->where('key', 'wizard')->sole();
        $character = app(CreateCharacter::class)->handle($owner, $game, ['name' => 'Mentor', 'character_class_definition_id' => $class->id]);
        $game->sessionCharacters()->where('character_id', $character->id)->update(['assigned_user_id' => $player->id]);

        return [$owner, $player, $game, $character];
    }
}
