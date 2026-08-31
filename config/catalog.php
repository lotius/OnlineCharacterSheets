<?php

use App\Models\CharacterClassDefinition;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\StatDefinition;
use App\Models\TrayDefinition;

return [
    'types' => [
        'items' => ['label' => 'Items', 'model' => ItemDefinition::class],
        'spells' => ['label' => 'Spells', 'model' => SpellDefinition::class],
        'skills' => ['label' => 'Skills', 'model' => SkillDefinition::class],
        'classes' => ['label' => 'Character Classes', 'model' => CharacterClassDefinition::class],
        'stats' => ['label' => 'Stat Types', 'model' => StatDefinition::class],
        'slots' => ['label' => 'Equipment Slots', 'model' => SlotDefinition::class],
        'trays' => ['label' => 'Sheet Sections', 'model' => TrayDefinition::class],
    ],
];
