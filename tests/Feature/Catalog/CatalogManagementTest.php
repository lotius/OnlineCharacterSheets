<?php

namespace Tests\Feature\Catalog;

use App\Actions\Characters\CreateCharacter;
use App\Actions\GameSessions\CreateGameSession;
use App\Models\CharacterClassDefinition;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use App\Models\User;
use Database\Seeders\HeroQuestGameSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HeroQuestGameSystemSeeder::class);
    }

    public function test_only_administrators_can_manage_system_wide_definitions(): void
    {
        $version = GameSystemVersion::query()->sole();
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->get('/admin/catalog')->assertForbidden();
        $this->actingAs($admin)->post("/admin/catalog/{$version->id}/items", [
            'name' => 'System Relic',
            'key' => 'system_relic',
        ])->assertRedirect();

        $this->assertDatabaseHas('item_definitions', [
            'key' => 'system_relic',
            'owner_user_id' => null,
            'scope_key' => 'system',
        ]);
    }

    public function test_heroquest_core_version_displays_as_heroquest(): void
    {
        $version = GameSystemVersion::query()->with('gameSystem')->sole();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertSame('HeroQuest', $version->display_name);

        $this->actingAs($admin)
            ->get('/admin/catalog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Catalog/Index')
                ->where('versions.0.name', 'HeroQuest'));
    }

    public function test_administrators_can_create_game_elements_piecemeal(): void
    {
        $version = GameSystemVersion::query()->sole();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post("/admin/catalog/{$version->id}/classes", [
            'name' => 'Knight',
            'key' => 'knight',
            'description' => 'A sturdy armored hero.',
            'display_order' => 50,
            'configuration_json' => json_encode(['default_stats' => ['body' => 7, 'mind' => 3]]),
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('character_class_definitions', [
            'game_system_version_id' => $version->id,
            'owner_user_id' => null,
            'scope_key' => 'system',
            'key' => 'knight',
            'name' => 'Knight',
        ]);

        $this->assertSame(
            ['body' => 7, 'mind' => 3],
            CharacterClassDefinition::query()->where('key', 'knight')->sole()->configuration['default_stats'],
        );
    }

    public function test_homebrew_belongs_to_a_dm_account_and_is_reusable_across_their_rooms(): void
    {
        $version = GameSystemVersion::query()->sole();
        $dm = User::factory()->create();
        $otherDm = User::factory()->create();
        $firstRoom = app(CreateGameSession::class)->handle($dm, $version, ['name' => 'First']);
        $secondRoom = app(CreateGameSession::class)->handle($dm, $version, ['name' => 'Second']);
        $otherRoom = app(CreateGameSession::class)->handle($otherDm, $version, ['name' => 'Other']);

        $this->actingAs($dm)->post("/library/{$version->id}/items", [
            'name' => 'Clockwork Blade',
            'key' => 'clockwork_blade',
        ])->assertRedirect();

        $item = ItemDefinition::query()->where('key', 'clockwork_blade')->sole();
        $this->assertSame($dm->id, $item->owner_user_id);
        $this->assertSame("user:{$dm->id}", $item->scope_key);

        foreach ([$firstRoom, $secondRoom] as $room) {
            $character = app(CreateCharacter::class)->handle($dm, $room, ['name' => 'Hero']);
            $this->actingAs($dm)->post("/characters/{$character->id}/items", [
                'item_definition_id' => $item->id,
                'quantity' => 1,
            ])->assertRedirect()->assertSessionDoesntHaveErrors();
        }

        $outsider = app(CreateCharacter::class)->handle($otherDm, $otherRoom, ['name' => 'Outsider']);
        $this->actingAs($otherDm)->post("/characters/{$outsider->id}/items", [
            'item_definition_id' => $item->id,
            'quantity' => 1,
        ])->assertSessionHasErrors('item_definition_id');
    }

    public function test_a_user_cannot_edit_another_users_homebrew(): void
    {
        $version = GameSystemVersion::query()->sole();
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $item = ItemDefinition::query()->create([
            'game_system_version_id' => $version->id,
            'owner_user_id' => $owner->id,
            'scope_key' => "user:{$owner->id}",
            'key' => 'private_relic',
            'name' => 'Private Relic',
        ]);

        $this->actingAs($attacker)->patch("/library/{$version->id}/items/{$item->id}", [
            'name' => 'Stolen',
            'key' => 'stolen',
        ])->assertNotFound();
    }
}
