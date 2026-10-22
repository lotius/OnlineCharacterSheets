<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCharacterItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999', 'required_without:delta'],
            'delta' => ['nullable', 'integer', 'between:-999,999', 'prohibits:quantity'],
        ];
    }
}
