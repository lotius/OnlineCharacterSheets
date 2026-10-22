<?php

namespace App\Http\Requests\Characters;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterStatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'value' => ['nullable', 'integer', 'between:-1000000,1000000', 'required_without_all:delta,max_value'],
            'delta' => ['nullable', 'integer', 'between:-1000000,1000000', 'prohibits:value'],
            'max_value' => ['nullable', 'integer', 'between:-1000000,1000000'],
        ];
    }
}
