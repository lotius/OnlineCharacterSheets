<?php

namespace Database\Seeders;

use App\Models\CharacterClassDefinition;
use App\Models\GameSystem;
use App\Models\GameSystemVersion;
use App\Models\SlotDefinition;
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
            ['name' => 'Core', 'is_current' => true, 'configuration' => []],
        );

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
            'barbarian' => ['body' => 8, 'mind' => 2],
            'dwarf' => ['body' => 7, 'mind' => 3],
            'elf' => ['body' => 6, 'mind' => 4],
            'wizard' => ['body' => 4, 'mind' => 6],
        ] as $key => $stats) {
            CharacterClassDefinition::query()->updateOrCreate(
                ['game_system_version_id' => $version->id, 'key' => $key],
                ['name' => str($key)->title(), 'configuration' => ['default_stats' => $stats], 'display_order' => array_search($key, ['barbarian', 'dwarf', 'elf', 'wizard'], true) * 10],
            );
        }
    }
}
