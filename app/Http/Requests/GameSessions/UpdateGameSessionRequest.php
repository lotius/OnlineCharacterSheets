<?php

namespace App\Http\Requests\GameSessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('game')?->owner_user_id;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'visibility' => ['required', Rule::in(['private', 'invite_only', 'public_view'])],
        ];
    }
}
