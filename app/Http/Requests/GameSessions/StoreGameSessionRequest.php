<?php

namespace App\Http\Requests\GameSessions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'game_system_version_id' => ['required', 'integer', Rule::exists('game_system_versions', 'id')->where('is_current', true)],
            'visibility' => ['required', Rule::in(['private', 'invite_only', 'public_view'])],
        ];
    }
}
