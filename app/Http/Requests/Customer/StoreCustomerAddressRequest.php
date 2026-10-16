<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetCustomerId = $this->input('customer_id');

        $isOwner = auth()->check() && auth()->user()->customer_id == $targetCustomerId;
        $isGuest = session('guest_customer_id') == $targetCustomerId;

        return $isOwner || $isGuest;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'zipcode' => ['nullable', 'string', 'max:15'],
            'street' => ['required', 'string', 'max:255'],

            // 🚀 AQUI: Liberamos a passagem do ID da Rua
            'street_id' => ['nullable', 'integer'],

            'neighborhood' => ['required', 'string', 'max:100'],

            // 🚀 AQUI: Liberamos a passagem do ID do Bairro
            'neighborhood_id' => ['nullable', 'integer'],

            'number' => ['required_if:noNumber,false', 'nullable', 'string', 'max:20'],
            'noNumber' => ['required', 'boolean'],
            'complement' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'street.required' => 'O nome da rua é obrigatório.',
            'neighborhood.required' => 'O bairro não foi identificado.',
            'number.required_if' => 'Informe o número da residência ou marque "S/N (sem número)".',
        ];
    }
}
