<?php

namespace Database\Seeders;

use App\Models\CharacterClassDefinition;
use App\Models\GameSystem;
use App\Models\GameSystemVersion;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\StatDefinition;
use App\Models\TrayDefinition;
use Illuminate\Database\Seeder;

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
            ['name' => null, 'is_current' => true, 'configuration' => []],
        );

        $classDisplayOrder = 10;

        foreach ([
            ['key' => 'body', 'name' => 'Body Points', 'default_value' => 6, 'default_max_value' => 6, 'display_order' => 10],
            ['key' => 'mind', 'name' => 'Mind Points', 'default_value' => 3, 'default_max_value' => 3, 'display_order' => 20],
        ] as $stat) {
            StatDefinition::query()->updateOrCreate(
                ['game_system_version_id' => $version->id, 'key' => $stat['key']],
                [...$stat, 'data_type' => 'integer', 'minimum_value' => 0, 'is_public' => true],
            );
        }

        foreach (['head', 'body', 'left_hand', 'right_hand'] as $order => $key) {
            SlotDefinition::query()->updateOrCreate(
                ['game_system_version_id' => $version->id, 'key' => $key],
                ['name' => str($key)->replace('_', ' ')->title(), 'capacity' => 1, 'display_order' => ($order + 1) * 10],
            );
        }

        foreach (['equipment', 'inventory', 'spells', 'skills', 'artifacts', 'notes'] as $order => $key) {
            TrayDefinition::query()->updateOrCreate(
                ['game_system_version_id' => $version->id, 'key' => $key],
                ['name' => str($key)->title(), 'display_order' => ($order + 1) * 10, 'is_collapsible' => true, 'default_open' => $order < 2],
            );
        }

        foreach ([
            'barbarian' => ['name' => 'Barbarian', 'description' => 'A powerful warrior with exceptional strength.', 'body' => 8, 'mind' => 2, 'starting_equipment' => ['broadsword']],
            'wizard' => ['name' => 'Wizard', 'description' => 'A master of elemental magic.', 'body' => 4, 'mind' => 6, 'starting_equipment' => ['dagger']],
            'dwarf' => ['name' => 'Dwarf', 'description' => 'A powerful warrior with a knack for disarming traps.', 'body' => 7, 'mind' => 3, 'starting_equipment' => ['short_sword']],
            'elf' => ['name' => 'Elf', 'description' => 'A warrior with an affinity for magic.', 'body' => 6, 'mind' => 4, 'starting_equipment' => ['short_sword']],
            'bard' => ['name' => 'Bard', 'description' => 'A swashbuckling hero who inspires fellow heroes.', 'body' => 5, 'mind' => 4, 'starting_equipment' => ['rapier']],
            'druid' => ['name' => 'Druid', 'description' => 'A magic user who calls upon nature for strength.', 'body' => 6, 'mind' => 4, 'starting_equipment' => ['dagger']],
            'warlock' => ['name' => 'Warlock', 'description' => 'A sorcerer who uses the dark arts.', 'body' => 4, 'mind' => 5, 'starting_equipment' => ['wand']],
            'knight' => ['name' => 'Knight', 'description' => 'A skilled warrior who defends his fellow heroes.', 'body' => 7, 'mind' => 2, 'starting_equipment' => ['short_sword', 'shield']],
            'rogue' => ['name' => 'Rogue', 'description' => 'A cunning warrior who outmaneuvers his foes.', 'body' => 5, 'mind' => 4, 'starting_equipment' => ['dagger', 'bandolier']],
            'monk' => ['name' => 'Monk', 'description' => 'An unarmed hero who uses the 4 elements for strength.', 'body' => 6, 'mind' => 4, 'starting_equipment' => []],
            'berserker' => ['name' => 'Berserker', 'description' => 'A raging warrior who fights with strength and recklessness.', 'body' => 7, 'mind' => 2, 'starting_equipment' => ['broadsword']],
            'explorer' => ['name' => 'Explorer', 'description' => 'A dwarven adventurer who seeks riches and glory.', 'body' => 5, 'mind' => 5, 'starting_equipment' => ['hand_axe']],
        ] as $key => $class) {
            CharacterClassDefinition::query()->updateOrCreate(
                ['game_system_version_id' => $version->id, 'key' => $key],
                [
                    'name' => $class['name'],
                    'description' => $class['description'],
                    'configuration' => [
                        'default_stats' => ['body' => $class['body'], 'mind' => $class['mind']],
                        'starting_equipment' => $class['starting_equipment'],
                    ],
                    'display_order' => $classDisplayOrder,
                ],
            );

            $classDisplayOrder += 10;
        }

        foreach ([
            'broadsword' => ['name' => 'Broadsword', 'description' => 'A reliable one-handed weapon.'],
            'dagger' => ['name' => 'Dagger', 'description' => 'A small, light blade.'],
            'short_sword' => ['name' => 'Short Sword', 'description' => 'A balanced one-handed sword.'],
            'rapier' => ['name' => 'Rapier', 'description' => 'A light sword suited to a swashbuckling hero.'],
            'wand' => ['name' => 'Wand', 'description' => 'A focus for arcane power.'],
            'bandolier' => ['name' => 'Bandolier', 'description' => 'A convenient belt for carrying a rogue’s tools.'],
            'hand_axe' => ['name' => 'Hand Axe', 'description' => 'A compact axe suited to an explorer.'],
            'shield' => ['name' => 'Shield', 'description' => 'Defensive equipment carried in one hand.'],
            'helmet' => ['name' => 'Helmet', 'description' => 'Protective head equipment.'],
            'healing_potion' => ['name' => 'Healing Potion', 'description' => 'A consumable restorative draught.'],
            'battle_axe' => ['name' => 'Battle Axe', 'description' => 'A heavy two-handed weapon.', 'configuration' => ['required_slots' => ['left_hand', 'right_hand']]],
        ] as $key => $item) {
            ItemDefinition::query()->updateOrCreate(['game_system_version_id' => $version->id, 'key' => $key], $item);
        }

        foreach ([
            'heal_body' => ['name' => 'Heal Body', 'description' => 'Restore lost Body Points.'],
            'fire_of_wrath' => ['name' => 'Fire of Wrath', 'description' => 'A direct magical attack.'],
        ] as $key => $spell) {
            SpellDefinition::query()->updateOrCreate(['game_system_version_id' => $version->id, 'key' => $key], $spell);
        }

        foreach ([
            'disarm_trap' => ['name' => 'Disarm Trap', 'description' => 'Safely disable a discovered trap.'],
            'battle_rage' => ['name' => 'Battle Rage', 'description' => 'A configurable martial ability.'],
        ] as $key => $skill) {
            SkillDefinition::query()->updateOrCreate(['game_system_version_id' => $version->id, 'key' => $key], $skill);
        }
    }
}
