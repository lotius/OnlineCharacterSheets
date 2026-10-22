<?php

namespace App\Actions\Characters;

use App\Models\Character;
use App\Models\CharacterStat;
use App\Services\CharacterMutation;
use Illuminate\Validation\ValidationException;

class UpdateCharacterStat
{
    /**
     * Deltas are applied to the locked current value and clamped to the stat's bounds, so rapid taps from
     * several devices never overwrite each other. An absolute value outside the bounds is rejected.
     *
     * @param  array{value?: int|null, delta?: int|null, max_value?: int|null}  $change
     */
    public function handle(Character $character, CharacterStat $stat, array $change): CharacterStat
    {
        if ($stat->character_id !== $character->id) {
            throw ValidationException::withMessages(['stat' => 'That stat does not belong to this character.']);
        }

        return app(CharacterMutation::class)->run($character, 'stat.changed', 'stat', $stat->id, function () use ($stat, $change): CharacterStat {
            $stat->refresh();
            $definition = $stat->definition;
            $minimum = $definition->minimum_value === null ? null : (int) $definition->minimum_value;
            $attributes = [];

            if (array_key_exists('max_value', $change) && $change['max_value'] !== null) {
                if (($definition->kind ?? 'resource') !== 'resource') {
                    throw ValidationException::withMessages(['max_value' => 'Only resource stats have a maximum.']);
                }
                if (($minimum !== null && $change['max_value'] < $minimum) || ($definition->maximum_value !== null && $change['max_value'] > (int) $definition->maximum_value)) {
                    throw ValidationException::withMessages(['max_value' => 'That maximum is outside the allowed range.']);
                }
                $attributes['max_value'] = $change['max_value'];
            }

            $maxValue = $attributes['max_value'] ?? $stat->max_value;
            $maximum = $definition->maximum_value === null ? ($maxValue === null ? null : (int) $maxValue) : (int) $definition->maximum_value;
            $value = (int) $stat->value;

            if (isset($change['delta'])) {
                $value += $change['delta'];
                $value = $minimum === null ? $value : max($minimum, $value);
                $value = $maximum === null ? $value : min($maximum, $value);
            } elseif (isset($change['value'])) {
                if (($minimum !== null && $change['value'] < $minimum) || ($maximum !== null && $change['value'] > $maximum)) {
                    throw ValidationException::withMessages(['value' => "Value must be between {$minimum} and {$maximum}."]);
                }
                $value = $change['value'];
            } elseif ($maximum !== null && $value > $maximum) {
                $value = $maximum; // Lowering the maximum pulls the current value down with it.
            }

            $stat->update($attributes + ['value' => $value]);

            return $stat->refresh();
        });
    }
}
