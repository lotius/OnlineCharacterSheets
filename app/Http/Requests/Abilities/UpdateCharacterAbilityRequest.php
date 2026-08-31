<?php

namespace App\Http\Requests\Abilities;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterAbilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['is_used' => ['sometimes', 'boolean'], 'charges' => ['nullable', 'integer', 'min:0', 'max:999']];
    }
}
