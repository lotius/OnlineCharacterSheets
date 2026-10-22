<?php

namespace App\Services;

use App\Models\Character;
use App\Models\GameSession;
use App\Models\ItemDefinition;
use App\Models\SkillDefinition;
use App\Models\SlotDefinition;
use App\Models\SpellDefinition;
use App\Models\TrayDefinition;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CharacterSheetState
{
    /** @return array<string, mixed> */
    public function forViewer(?User $user, GameSession $game, Character $character): array
    {
        return DB::transaction(function () use ($user, $game, $character) {
            $game = GameSession::query()->lockForUpdate()->findOrFail($game->id);
            $character = Character::query()->findOrFail($character->id);
            abort_unless($game->sessionCharacters()->where('is_active', true)->where('character_id', $character->id)->exists(), 404);

            $isMember = $user?->can('view', $game) ?? false;
            abort_unless($game->visibility === 'public_view' || $isMember, 403);
            $canUpdate = $isMember && ($user?->can('update', $character) ?? false);
            $character->load(['characterClass', 'stats.definition', 'items.definition', 'items.slots.definition', 'spells.definition', 'skills.definition', 'note', 'gameSystemVersion.trays', 'gameSystemVersion.slots', 'gameSystemVersion.items', 'gameSystemVersion.spells', 'gameSystemVersion.skills']);
            $available = fn (string $model) => $model::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('name')->get();
            $trays = TrayDefinition::query()->availableToUser($game->owner)->where('game_system_version_id', $game->game_system_version_id)->orderBy('display_order')->get();

            $payload = ['game' => ['id' => $game->id, 'name' => $game->name, 'is_spectator' => ! $isMember, 'current_character_id' => $game->current_character_id, 'current_revision' => $game->current_revision], 'character' => [
                'id' => $character->id, 'name' => $character->name, 'class_name' => $character->characterClass?->name, 'gold' => $character->gold, 'can_update' => $canUpdate,
                'stats' => $character->stats->filter(fn ($stat) => $isMember || $stat->definition->is_public)->sortBy('definition.display_order')->values()->map(fn ($stat) => ['id' => $stat->id, 'name' => $stat->definition->name, 'value' => $stat->value, 'max_value' => $stat->max_value]),
                'trays' => $trays->map(fn ($tray) => ['key' => $tray->key, 'name' => $tray->name, 'default_open' => $tray->default_open]),
                'slots' => $available(SlotDefinition::class)->map(fn ($slot) => ['id' => $slot->id, 'name' => $slot->name]),
                'item_definitions' => $canUpdate ? $available(ItemDefinition::class)->map(fn ($item) => ['id' => $item->id, 'name' => $item->name]) : [],
                'items' => $character->items->map(fn ($item) => ['id' => $item->id, 'name' => $item->custom_name ?? $item->definition?->name, 'description' => $item->custom_description ?? $item->definition?->description, 'icon' => $item->definition?->icon ? '/storage/'.$item->definition->icon : null, 'quantity' => $item->quantity, 'is_active' => $item->is_active, 'slots' => $item->slots->map(fn ($slot) => ['id' => $slot->definition->id, 'name' => $slot->definition->name])]),
                'spell_definitions' => $canUpdate ? $available(SpellDefinition::class)->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->name]) : [],
                'spells' => $character->spells->map(fn ($spell) => ['id' => $spell->id, 'name' => $spell->definition->name, 'description' => $spell->definition->description, 'icon' => $spell->definition->icon ? '/storage/'.$spell->definition->icon : null, 'is_used' => $spell->is_used, 'charges' => $spell->charges]),
                'skill_definitions' => $canUpdate ? $available(SkillDefinition::class)->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name]) : [],
                'skills' => $character->skills->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->definition->name, 'description' => $skill->definition->description, 'icon' => $skill->definition->icon ? '/storage/'.$skill->definition->icon : null, 'is_used' => $skill->is_used, 'charges' => $skill->charges]),
                'note' => ! $character->note || (! $character->note->is_private || $canUpdate) ? ['content' => $character->note?->content, 'is_private' => $character->note?->is_private ?? false] : null,
            ]];
            return $isMember ? $payload : app(PublicGameState::class)->sheet($payload);
        });
    }
}
