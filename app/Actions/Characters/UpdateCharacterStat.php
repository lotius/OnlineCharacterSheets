<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterStat;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UpdateCharacterStat
{
    public function handle(Character $character, CharacterStat $stat, int $value): CharacterStat
    {
        if ($stat->character_id !== $character->id) {
            throw ValidationException::withMessages(['stat' => 'That stat does not belong to this character.']);
        }

        $definition = $stat->definition;
        $minimum = $definition->minimum_value === null ? null : (int) $definition->minimum_value;
        $maximum = $definition->maximum_value === null ? ($stat->max_value === null ? null : (int) $stat->max_value) : (int) $definition->maximum_value;

        if (($minimum !== null && $value < $minimum) || ($maximum !== null && $value > $maximum)) {
            throw ValidationException::withMessages(['value' => "Value must be between {$minimum} and {$maximum}."]);
        }

        return app(CharacterMutation::class)->run($character, 'stat.changed', 'stat', $stat->id, function () use ($stat, $value): CharacterStat {
            $stat->refresh()->update(['value' => $value]);

            return $stat->refresh();
        });
    }
}
