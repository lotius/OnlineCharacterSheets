<?php

namespace Tests\Feature\Characters;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Events\RoomRealtimePinged;
use App\Models\Character;
use App\Models\CharacterClassDefinition;
use App\Models\CharacterItem;
use App\Models\GameSession;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ItemRulesTest extends TestCase
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
        $this->game = app(CreateGameSession::class)->handle($this->owner, GameSystemVersion::query()->sole(), ['name' => 'Rules']);
    }

    private function hero(string $class): Character
    {
        $classId = CharacterClassDefinition::query()->where('key', $class)->value('id');

        return app(CreateCharacter::class)->handle($this->owner, $this->game, ['name' => ucfirst($class), 'character_class_definition_id' => $classId]);
    }

    private function give(Character $character, string $key): int
    {
        $definition = ItemDefinition::query()->where('key', $key)->sole();
        $this->actingAs($this->owner)->postJson("/characters/{$character->id}/items", ['item_definition_id' => $definition->id, 'quantity' => 1])->assertOk();

        return $character->items()->where('item_definition_id', $definition->id)->latest('id')->value('id');
    }

    /** @return list<string> */
    private function slotsOf(int $itemId): array
    {
        return CharacterItem::query()->findOrFail($itemId)->slots()->with('definition')->get()->pluck('definition.key')->sort()->values()->all();
    }

    public function test_one_tap_equip_picks_the_preferred_free_slot_and_two_handed_items_take_both_hands(): void
    {
        $barbarian = $this->hero('barbarian'); // starts with a broadsword in the main hand
        $shield = $this->give($barbarian, 'shield');
        $this->putJson("/characters/{$barbarian->id}/items/{$shield}/equip")->assertOk()->assertJsonPath('warnings', []);
        $this->assertSame(['left_hand'], $this->slotsOf($shield));

        $axe = $this->give($barbarian, 'battle_axe');
        $this->putJson("/characters/{$barbarian->id}/items/{$axe}/equip")->assertOk();
        $this->assertSame(['left_hand', 'right_hand'], $this->slotsOf($axe));
        $this->assertSame([], $this->slotsOf($shield), 'The shield is swapped out by the two-handed axe.');
        $this->assertSame(1, $barbarian->items()->whereHas('slots')->count());
    }

    public function test_class_restrictions_warn_by_default_and_block_in_enforce_mode(): void
    {
        $wizard = $this->hero('wizard');
        $helmet = $this->give($wizard, 'helmet');
        $this->putJson("/characters/{$wizard->id}/items/{$helmet}/equip")->assertOk()
            ->assertJsonPath('warnings.0', 'Wizard cannot normally use Helmet.')
            ->assertJsonPath('character.items.1.restriction', 'Wizard cannot normally use Helmet.');

        $version = GameSystemVersion::query()->sole();
        $version->update(['configuration' => ['rules_mode' => 'enforce'] + $version->configuration]);
        $shield = $this->give($wizard, 'shield');
        $this->putJson("/characters/{$wizard->id}/items/{$shield}/equip")->assertUnprocessable()->assertJsonValidationErrors('item');
        $this->assertSame([], $this->slotsOf($shield));
    }

    public function test_presented_items_carry_modifiers_for_derived_stats(): void
    {
        $dwarf = $this->hero('dwarf');
        $plate = $this->give($dwarf, 'plate_mail');
        $response = $this->putJson("/characters/{$dwarf->id}/items/{$plate}/equip")->assertOk();
        $item = collect($response->json('character.items'))->firstWhere('key', 'plate_mail');
        $this->assertSame([['stat' => 'defend', 'op' => 'add', 'value' => 2], ['stat' => 'move', 'op' => 'set', 'value' => 1]], $item['modifiers']);
        $this->assertSame(['body'], collect($item['slots'])->pluck('key')->all());
        $this->assertSame('attribute', collect($response->json('character.stats'))->firstWhere('key', 'defend')['kind']);
    }

    public function test_rapid_deltas_accumulate_instead_of_overwriting_each_other(): void
    {
        $hero = $this->hero('barbarian');
        $body = $hero->stats()->whereHas('definition', fn ($query) => $query->where('key', 'body'))->sole();
        foreach ([-1, -1, -1] as $delta) {
            $this->actingAs($this->owner)->patchJson("/characters/{$hero->id}/stats/{$body->id}", ['delta' => $delta])->assertOk();
        }
        $this->assertSame(5, $body->refresh()->value);
        $this->patchJson("/characters/{$hero->id}/stats/{$body->id}", ['delta' => 99])->assertOk();
        $this->assertSame(8, $body->refresh()->value, 'Deltas clamp to the maximum.');
        $this->assertSame(4, $hero->refresh()->revision);
        Event::assertDispatched(RoomRealtimePinged::class, fn ($event) => $event->characterId === $hero->id && $event->characterRevision !== null);
    }

    public function test_lowering_a_resource_maximum_pulls_the_value_down(): void
    {
        $hero = $this->hero('barbarian');
        $body = $hero->stats()->whereHas('definition', fn ($query) => $query->where('key', 'body'))->sole();
        $this->actingAs($this->owner)->patchJson("/characters/{$hero->id}/stats/{$body->id}", ['max_value' => 6])->assertOk();
        $this->assertSame([6, 6], [$body->refresh()->value, $body->max_value]);
        $attack = $hero->stats()->whereHas('definition', fn ($query) => $query->where('key', 'attack'))->sole();
        $this->patchJson("/characters/{$hero->id}/stats/{$attack->id}", ['max_value' => 6])->assertUnprocessable();
    }

    public function test_catalog_rejects_malformed_item_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $version = GameSystemVersion::query()->sole();
        $this->actingAs($admin)->post("/admin/catalog/{$version->id}/items", [
            'name' => 'Bad Sword', 'configuration_json' => json_encode(['modifiers' => [['stat' => 'attack', 'op' => 'multiply', 'value' => 2]]]),
        ])->assertSessionHasErrors('modifiers.0.op');
    }
}
