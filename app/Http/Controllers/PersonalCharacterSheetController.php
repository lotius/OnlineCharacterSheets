<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameSession;
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

        return Inertia::render('Characters/Show', ['game' => ['id' => $game->id, 'name' => $game->name], 'character' => [
            'id' => $character->id, 'name' => $character->name, 'class_name' => $character->characterClass?->name, 'can_update' => $canUpdate,
            'stats' => $character->stats->sortBy('definition.display_order')->values()->map(fn ($stat) => ['id' => $stat->id, 'name' => $stat->definition->name, 'value' => $stat->value, 'max_value' => $stat->max_value]),
            'trays' => $character->gameSystemVersion->trays->sortBy('display_order')->values()->map(fn ($tray) => ['key' => $tray->key, 'name' => $tray->name, 'default_open' => $tray->default_open]),
            'slots' => $character->gameSystemVersion->slots->sortBy('display_order')->values()->map(fn ($slot) => ['id' => $slot->id, 'name' => $slot->name]),
            'item_definitions' => $character->gameSystemVersion->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]),
            'items' => $character->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->custom_name ?? $item->definition?->name, 'description' => $item->custom_description ?? $item->definition?->description, 'quantity' => $item->quantity, 'slots' => $item->slots->map(fn ($slot) => ['id' => $slot->definition->id, 'name' => $slot->definition->name])]),
            'spell_definitions' => $character->gameSystemVersion->spells->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->name]),
            'spells' => $character->spells->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->definition->name, 'description' => $spell->definition->description, 'is_used' => $spell->is_used, 'charges' => $spell->charges]),
            'skill_definitions' => $character->gameSystemVersion->skills->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name]),
            'skills' => $character->skills->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->definition->name, 'description' => $skill->definition->description, 'is_used' => $skill->is_used, 'charges' => $skill->charges]),
            'note' => ! $character->note || (! $character->note->is_private || $canUpdate) ? ['content' => $character->note?->content, 'is_private' => $character->note?->is_private ?? false] : null,
        ]]);
    }
}
