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
        return ['value' => ['required', 'integer']];
    }
}
