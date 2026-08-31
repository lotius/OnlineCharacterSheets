<?php

namespace App\Http\Requests\Abilities;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['skill_definition_id' => ['required', 'integer', 'exists:skill_definitions,id']];
    }
}
