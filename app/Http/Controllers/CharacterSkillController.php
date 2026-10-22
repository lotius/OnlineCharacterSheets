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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CharacterSkillController extends Controller
{
    public function store(StoreCharacterSkillRequest $request, Character $character, AddCharacterSkill $add): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $add->handle($character, SkillDefinition::query()->findOrFail($request->integer('skill_definition_id')));

        return $this->characterResponse($request, $character, success: 'Skill added.');
    }

    public function update(UpdateCharacterAbilityRequest $request, Character $character, CharacterSkill $skill, UpdateCharacterSkill $update): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $update->handle($character, $skill, $request->validated());

        return $this->characterResponse($request, $character);
    }

    public function destroy(Request $request, Character $character, CharacterSkill $skill, RemoveCharacterSkill $remove): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $character);
        $remove->handle($character, $skill);

        return $this->characterResponse($request, $character);
    }
}
