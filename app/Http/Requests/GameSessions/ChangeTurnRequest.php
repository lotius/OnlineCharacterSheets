<?php

namespace App\Http\Requests\GameSessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['select', 'next', 'previous', 'first'])],
            'character_id' => ['required_if:direction,select', 'nullable', 'integer'],
        ];
    }
}
