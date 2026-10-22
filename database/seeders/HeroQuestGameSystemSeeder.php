<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\CharacterClassDefinition;
use App\Models\GameSystem;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\StatDefinition;
use App\Models\TrayDefinition;
use App\Services\ItemRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * HeroQuest as configuration. All game specifics (stats, slots, items, rules, layouts) are data;
 * the catalog itself lives in database/data/heroquest/*.json in the same format as a catalog import.
 */
class HeroQuestGameSystemSeeder extends Seeder
{
    public function run(): void
    {
        $system = GameSystem::query()->updateOrCreate(
            ['slug' => 'heroquest'],
            ['name' => 'HeroQuest', 'description' => 'A generic configuration for HeroQuest-style dungeon crawls.', 'default_theme' => 'heroquest', 'is_active' => true],
        );

        $version = GameSystemVersion::query()->updateOrCreate(
            ['game_system_id' => $system->id, 'version' => '1.0'],
            ['name' => null, 'is_current' => true, 'configuration' => [
                'rules_mode' => 'warn',
                'item_categories' => [
                    ['key' => 'weapon', 'name' => 'Weapons'], ['key' => 'armor', 'name' => 'Armor'],
                    ['key' => 'potion', 'name' => 'Potions'], ['key' => 'artifact', 'name' => 'Artifacts'],
                    ['key' => 'tool', 'name' => 'Tools'],
                ],
                'layouts' => [
                    'sheet' => [
                        ['id' => 'stats', 'type' => 'stats'],
                        ['id' => 'equipment', 'type' => 'equipment', 'title' => 'Equipped'],
                        ['id' => 'potions', 'type' => 'items', 'title' => 'Potions', 'category' => 'potion'],
                        ['id' => 'artifacts', 'type' => 'items', 'title' => 'Artifacts', 'category' => 'artifact'],
                        ['id' => 'inventory', 'type' => 'items', 'title' => 'Inventory', 'exclude_categories' => ['potion', 'artifact']],
                        ['id' => 'spells', 'type' => 'abilities', 'kind' => 'spells', 'title' => 'Spells'],
                        ['id' => 'skills', 'type' => 'abilities', 'kind' => 'skills', 'title' => 'Skills', 'collapsed' => true],
                        ['id' => 'notes', 'type' => 'notes', 'title' => 'Notes', 'collapsed' => true],
                    ],
                    'card' => [
                        ['id' => 'stats', 'type' => 'stats'],
                        ['id' => 'equipment', 'type' => 'equipment', 'title' => 'Equipped'],
                        ['id' => 'potions', 'type' => 'items', 'title' => 'Potions', 'category' => 'potion'],
                        ['id' => 'inventory', 'type' => 'items', 'title' => 'Inventory', 'collapsed' => true, 'exclude_categories' => ['potion']],
                    ],
                ],
            ]],
        );

        // Attack/Defend/Movement are attributes: a base value that equipped items set or add to.
        foreach ([
            ['key' => 'body', 'name' => 'Body', 'kind' => 'resource', 'default_value' => 6, 'default_max_value' => 6, 'display_order' => 10],
            ['key' => 'mind', 'name' => 'Mind', 'kind' => 'resource', 'default_value' => 3, 'default_max_value' => 3, 'display_order' => 20],
            ['key' => 'attack', 'name' => 'Attack', 'kind' => 'attribute', 'default_value' => 1, 'default_max_value' => null, 'display_order' => 30, 'description' => 'Combat dice rolled when attacking (base 1, set by your weapon).'],
            ['key' => 'defend', 'name' => 'Defend', 'kind' => 'attribute', 'default_value' => 2, 'default_max_value' => null, 'display_order' => 40, 'description' => 'Combat dice rolled when defending (armor adds to this).'],
            ['key' => 'move', 'name' => 'Move', 'kind' => 'attribute', 'default_value' => 2, 'default_max_value' => null, 'display_order' => 50, 'description' => 'Red dice rolled for movement.'],
            ['key' => 'gold', 'name' => 'Gold', 'kind' => 'currency', 'default_value' => 0, 'default_max_value' => null, 'display_order' => 900],
        ] as $stat) {
            $this->upsert(StatDefinition::class, $version, $stat['key'], [...$stat, 'data_type' => 'integer', 'minimum_value' => 0, 'is_public' => true]);
        }

        foreach (['head' => 'Head', 'body' => 'Body', 'arms' => 'Arms', 'left_hand' => 'Off Hand', 'right_hand' => 'Main Hand'] as $key => $name) {
            $order = ['head' => 10, 'body' => 20, 'arms' => 25, 'left_hand' => 30, 'right_hand' => 40][$key];
            $this->upsert(SlotDefinition::class, $version, $key, ['name' => $name, 'capacity' => 1, 'display_order' => $order]);
        }

        foreach (['equipment', 'inventory', 'spells', 'skills', 'artifacts', 'notes'] as $order => $key) {
            $this->upsert(TrayDefinition::class, $version, $key, ['name' => str($key)->title(), 'display_order' => ($order + 1) * 10, 'is_collapsible' => true, 'default_open' => $order < 2]);
        }

        // Body/Mind are from the hero cards; the expansion heroes' values came from the earlier seed and should be verified.
        $classOrder = 10;
        foreach ([
            'barbarian' => ['Barbarian', 'A powerful warrior with exceptional strength.', 8, 2, ['broadsword']],
            'dwarf' => ['Dwarf', 'A sturdy warrior with a knack for disarming traps.', 7, 3, ['short_sword']],
            'elf' => ['Elf', 'A warrior with an affinity for magic.', 6, 4, ['short_sword']],
            'wizard' => ['Wizard', 'A master of elemental magic.', 4, 6, ['dagger']],
            'bard' => ['Bard', 'A swashbuckling hero who inspires fellow heroes.', 5, 4, ['rapier']],
            'druid' => ['Druid', 'A magic user who calls upon nature for strength.', 6, 4, ['dagger']],
            'warlock' => ['Warlock', 'A sorcerer who uses the dark arts.', 4, 5, ['wand']],
            'knight' => ['Knight', 'A skilled warrior who defends fellow heroes.', 7, 2, ['short_sword', 'shield']],
            'rogue' => ['Rogue', 'A cunning warrior who outmaneuvers foes.', 5, 4, ['dagger', 'bandolier']],
            'monk' => ['Monk', 'An unarmed hero who uses the four elements for strength.', 6, 4, []],
            'berserker' => ['Berserker', 'A raging warrior who fights with strength and recklessness.', 7, 2, ['broadsword']],
            'explorer' => ['Explorer', 'A dwarven adventurer who seeks riches and glory.', 5, 5, ['hand_axe']],
        ] as $key => [$name, $description, $body, $mind, $equipment]) {
            $this->upsert(CharacterClassDefinition::class, $version, $key, [
                'name' => $name, 'description' => $description, 'display_order' => $classOrder,
                'configuration' => ['default_stats' => ['body' => $body, 'mind' => $mind], 'starting_equipment' => $equipment],
            ]);
            $classOrder += 10;
        }

        $rules = app(ItemRules::class);
        foreach ($this->records('items') as $record) {
            $this->upsert(ItemDefinition::class, $version, $record['key'], $this->definition($record) + [
                'configuration' => $rules->validateConfiguration(Arr::only($record, ['category', 'cost', 'modifiers', 'allowed_slots', 'required_slots', 'exclude_classes', 'only_classes', 'consumable', 'tags', 'properties'])),
            ]);
        }
        foreach ($this->records('spells') as $record) {
            $this->upsert(SpellDefinition::class, $version, $record['key'], $this->definition($record) + ['configuration' => ['group' => $record['group'] ?? null]]);
        }

        foreach ([
            'disarm_trap' => ['name' => 'Disarm Trap', 'description' => 'Safely disable a discovered trap.'],
            'battle_rage' => ['name' => 'Battle Rage', 'description' => 'A configurable martial ability.'],
        ] as $key => $skill) {
            $this->upsert(SkillDefinition::class, $version, $key, $skill);
        }

        $this->backfillStats($version);
    }

    /** Characters created before a stat existed (e.g. Attack/Defend/Move) get it with class or default values. */
    private function backfillStats(GameSystemVersion $version): void
    {
        $definitions = StatDefinition::query()->where('game_system_version_id', $version->id)->whereNull('owner_user_id')->get();
        foreach (Character::query()->where('game_system_version_id', $version->id)->with(['stats', 'characterClass'])->lazy() as $character) {
            $classStats = $character->characterClass?->configuration['default_stats'] ?? [];
            foreach ($definitions->whereNotIn('id', $character->stats->pluck('stat_definition_id')) as $definition) {
                $value = $classStats[$definition->key] ?? $definition->default_value ?? 0;
                $character->stats()->create([
                    'stat_definition_id' => $definition->id,
                    'value' => $value,
                    'max_value' => $definition->kind === 'resource' ? ($classStats[$definition->key] ?? $definition->default_max_value) : null,
                ]);
            }
        }
    }

    /** @param class-string<Model> $model */
    private function upsert(string $model, GameSystemVersion $version, string $key, array $attributes): void
    {
        // System-wide rows only: never touch a user's homebrew definition that shares the key.
        $model::query()->updateOrCreate(
            ['game_system_version_id' => $version->id, 'scope_key' => 'system', 'key' => $key],
            $attributes + ['owner_user_id' => null],
        );
    }

    /** @return list<array<string, mixed>> */
    private function records(string $file): array
    {
        return json_decode(file_get_contents(database_path("data/heroquest/{$file}.json")), true, 512, JSON_THROW_ON_ERROR)['records'];
    }

    /** @return array<string, mixed> */
    private function definition(array $record): array
    {
        return ['name' => $record['name'], 'description' => $record['description'] ?? null, 'icon' => $record['icon'] ?? null];
    }
}
