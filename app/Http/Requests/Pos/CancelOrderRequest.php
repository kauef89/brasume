<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'pin' => ['nullable', 'string'],
            'reason' => ['required', 'string', 'max:255'], // Obrigamos a ter motivo para o cancelamento total
            'is_loss' => ['nullable', 'boolean'],
            'lost_items' => ['nullable', 'array'],
        ];
    }
}
