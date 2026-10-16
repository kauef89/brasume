<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class ManagerOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Apenas usuários logados no PDV (Caixas, Gerentes, Atendentes)
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:4'],
            'isLoss' => ['required', 'boolean'],
            // Se isLoss for true, o reason é estritamente obrigatório
            'reason' => ['required_if:isLoss,true', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_if' => 'Como houve desperdício, você deve informar o motivo.',
            'password.min' => 'A senha informada é muito curta.',
        ];
    }
}
