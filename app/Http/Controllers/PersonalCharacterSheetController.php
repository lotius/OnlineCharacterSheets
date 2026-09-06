<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameSession;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\TrayDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalCharacterSheetController extends Controller
{
    public function show(Request $request, GameSession $game, Character $character): Response
    {
        abort_unless($game->sessionCharacters()->where('character_id', $character->id)->exists(), 404);
        $user = $request->user();
        $isMember = $user?->can('view', $game) ?? false;
        abort_unless($game->visibility === 'public_view' || $isMember, 403);
        $canUpdate = $user?->can('update', $character) ?? false;
        $character->load(['characterClass', 'stats.definition', 'items.definition', 'items.slots.definition', 'spells.definition', 'skills.definition', 'note', 'gameSystemVersion.trays', 'gameSystemVersion.slots', 'gameSystemVersion.items', 'gameSystemVersion.spells', 'gameSystemVersion.skills']);
        $available = fn (string $model) => $model::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('name')->get();
        $trays = TrayDefinition::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('display_order')->get();

        return Inertia::render('Characters/Show', ['game' => ['id' => $game->id, 'name' => $game->name, 'is_spectator' => ! $isMember], 'character' => [
            'id' => $character->id, 'name' => $character->name, 'class_name' => $character->characterClass?->name, 'gold' => $character->gold, 'can_update' => $canUpdate,
            'stats' => $character->stats->sortBy('definition.display_order')->values()->map(fn ($stat) => ['id' => $stat->id, 'name' => $stat->definition->name, 'value' => $stat->value, 'max_value' => $stat->max_value]),
            'trays' => $trays->map(fn ($tray) => ['key' => $tray->key, 'name' => $tray->name, 'default_open' => $tray->default_open]),
            'slots' => $available(SlotDefinition::class)->map(fn ($slot) => ['id' => $slot->id, 'name' => $slot->name]),
            'item_definitions' => $available(ItemDefinition::class)->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]),
            'items' => $character->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->custom_name ?? $item->definition?->name, 'description' => $item->custom_description ?? $item->definition?->description, 'icon' => $item->definition?->icon ? '/storage/'.$item->definition->icon : null, 'quantity' => $item->quantity, 'is_active' => $item->is_active, 'slots' => $item->slots->map(fn ($slot) => ['id' => $slot->definition->id, 'name' => $slot->definition->name])]),
            'spell_definitions' => $available(SpellDefinition::class)->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->name]),
            'spells' => $character->spells->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->definition->name, 'description' => $spell->definition->description, 'icon' => $spell->definition->icon ? '/storage/'.$spell->definition->icon : null, 'is_used' => $spell->is_used, 'charges' => $spell->charges]),
            'skill_definitions' => $available(SkillDefinition::class)->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name]),
            'skills' => $character->skills->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->definition->name, 'description' => $skill->definition->description, 'icon' => $skill->definition->icon ? '/storage/'.$skill->definition->icon : null, 'is_used' => $skill->is_used, 'charges' => $skill->charges]),
            'note' => ! $character->note || (! $character->note->is_private || $canUpdate) ? ['content' => $character->note?->content, 'is_private' => $character->note?->is_private ?? false] : null,
        ]]);
    }
}
