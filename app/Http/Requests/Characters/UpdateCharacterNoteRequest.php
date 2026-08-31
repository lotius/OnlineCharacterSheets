<?php

namespace App\Http\Requests\Characters;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['content' => ['nullable', 'string', 'max:20000'], 'is_private' => ['required', 'boolean']];
    }
}
