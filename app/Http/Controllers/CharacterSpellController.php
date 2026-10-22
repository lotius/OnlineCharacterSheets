<?php

namespace App\Http\Controllers;

use App\Actions\Abilities\AddCharacterSpell;
use App\Actions\Abilities\RemoveCharacterSpell;
use App\Actions\Abilities\UpdateCharacterSpell;
use App\Http\Requests\Abilities\StoreCharacterSpellRequest;
use App\Http\Requests\Abilities\UpdateCharacterAbilityRequest;
use App\Models\Character;
use App\Models\CharacterSpell;
use App\Models\SpellDefinition;
use Illuminate\Http\RedirectResponse;

class CharacterSpellController extends Controller
{
    public function store(StoreCharacterSpellRequest $request, Character $character, AddCharacterSpell $add): RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, SpellDefinition::query()->findOrFail($request->integer('spell_definition_id')));

        return back()->with('success', 'Spell added.');
    }

    public function update(UpdateCharacterAbilityRequest $request, Character $character, CharacterSpell $spell, UpdateCharacterSpell $update): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $spell, $request->validated());

        return back();
    }

    public function destroy(Character $character, CharacterSpell $spell, RemoveCharacterSpell $remove): RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $spell);

        return back();
    }
}
