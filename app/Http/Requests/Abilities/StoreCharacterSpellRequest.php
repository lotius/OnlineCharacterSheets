<?php

namespace App\Http\Requests\Abilities;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterSpellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['spell_definition_id' => ['required', 'integer', 'exists:spell_definitions,id']];
    }
}
