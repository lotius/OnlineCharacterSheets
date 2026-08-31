<?php

namespace App\Http\Controllers;

use App\Actions\Abilities\AddCharacterSkill;
use App\Actions\Abilities\RemoveCharacterSkill;
use App\Actions\Abilities\UpdateCharacterSkill;
use App\Http\Requests\Abilities\StoreCharacterSkillRequest;
use App\Http\Requests\Abilities\UpdateCharacterAbilityRequest;
use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\SkillDefinition;
use Illuminate\Http\RedirectResponse;

class CharacterSkillController extends Controller
{
    public function store(StoreCharacterSkillRequest $request, Character $character, AddCharacterSkill $add): RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, SkillDefinition::query()->findOrFail($request->integer('skill_definition_id')));

        return back()->with('success', 'Skill added.');
    }

    public function update(UpdateCharacterAbilityRequest $request, Character $character, CharacterSkill $skill, UpdateCharacterSkill $update): RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $skill, $request->validated());

        return back();
    }

    public function destroy(Character $character, CharacterSkill $skill, RemoveCharacterSkill $remove): RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $skill);

        return back();
    }
}
