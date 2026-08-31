<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['item_definition_id' => ['nullable', 'integer', 'exists:item_definitions,id'], 'custom_name' => ['nullable', 'string', 'max:120'], 'custom_description' => ['nullable', 'string', 'max:2000'], 'quantity' => ['required', 'integer', 'min:1', 'max:999']];
    }
}
