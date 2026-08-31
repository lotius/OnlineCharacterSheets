<?php

namespace App\Http\Requests\Characters;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'character_class_definition_id' => ['nullable', 'integer', 'exists:character_class_definitions,id'],
        ];
    }
}
