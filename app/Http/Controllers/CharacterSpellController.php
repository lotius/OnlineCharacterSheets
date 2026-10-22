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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterSpellController extends Controller
{
    public function store(StoreCharacterSpellRequest $request, Character $character, AddCharacterSpell $add): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, SpellDefinition::query()->findOrFail($request->integer('spell_definition_id')));

        return $this->characterResponse($request, $character, success: 'Spell added.');
    }

    public function update(UpdateCharacterAbilityRequest $request, Character $character, CharacterSpell $spell, UpdateCharacterSpell $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $spell, $request->validated());

        return $this->characterResponse($request, $character);
    }

    public function destroy(Request $request, Character $character, CharacterSpell $spell, RemoveCharacterSpell $remove): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $spell);

        return $this->characterResponse($request, $character);
    }
}
