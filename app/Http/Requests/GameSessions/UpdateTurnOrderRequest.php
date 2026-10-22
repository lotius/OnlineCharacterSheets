<?php

namespace App\Http\Requests\GameSessions;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('game')) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['character_ids' => ['present', 'array', 'max:200'], 'character_ids.*' => ['required', 'integer', 'distinct']];
    }
}
