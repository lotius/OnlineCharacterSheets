<?php

namespace App\Http\Requests\Characters;

use Illuminate\Foundation\Http\FormRequest;

class AssignCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['assigned_user_id' => ['required', 'integer', 'exists:users,id']];
    }
}
