<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

class TeamOrderSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('team')->check() || auth()->check();
    }

    public function rules(): array
    {
        return [
            'slots'                               => ['required', 'array', 'min:1'],
            'slots.*.attendee_id'                 => ['required', 'integer'],

            // Itens de cada colaborador
            'slots.*.items'                       => ['required', 'array', 'min:1'],
            'slots.*.items.*.product_id'          => ['required', 'integer', 'exists:products,id'],
            'slots.*.items.*.qty'                 => ['required', 'numeric', 'min:0.001'],
            'slots.*.items.*.price'               => ['required', 'numeric', 'min:0'],
            'slots.*.items.*.extra_price'         => ['nullable', 'numeric', 'min:0'],
            'slots.*.items.*.is_takeaway'         => ['nullable', 'boolean'],
            'slots.*.items.*.notes'               => ['nullable', 'string', 'max:255'],

            // Modificadores do item
            'slots.*.items.*.modifiers'           => ['nullable', 'array'],
            'slots.*.items.*.modifiers.*.id'      => ['nullable'],
            'slots.*.items.*.modifiers.*.name'    => ['required_with:slots.*.items.*.modifiers', 'string'],
            'slots.*.items.*.modifiers.*.price'   => ['required_with:slots.*.items.*.modifiers', 'numeric'],
            'slots.*.items.*.modifiers.*.qty'     => ['required_with:slots.*.items.*.modifiers', 'numeric'],
        ];
    }

    // 🚀 SONDA DE VALIDAÇÃO: Grava no log o que chegou e por que falhou
    protected function failedValidation(Validator $validator)
    {
        Log::error('🚨 [TeamApp] Falha na validação do Lanche da Equipe:', [
            'erros' => $validator->errors()->toArray(),
            'payload_enviado' => $this->all()
        ]);

        parent::failedValidation($validator);
    }
}
