<?php

namespace App\Http\Requests\Customer;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $setting = CompanySetting::getCached() ?? CompanySetting::first();
        $mode = $setting?->customer_registration_mode ?? 'basic';
        $isCpfRequired = in_array($mode, ['cpf_required', 'customer_only', 'serpro_magic']);
        $requireBirth = (bool) ($setting?->require_birth_date ?? false);

        return [
            'name'       => ['required', 'string', 'min:3', 'max:255'],
            'email'      => ['nullable', 'string', 'email', 'max:255', Rule::unique('customers', 'email')],
            'password'   => ['required', 'string', 'min:6'],
            'phone'      => ['required', 'string', 'min:10'],
            'cpf'        => [
                $isCpfRequired ? 'required' : 'nullable',
                'string',
                Rule::unique('customers', 'cpf'),
            ],
            'birth_date' => [
                $requireBirth ? 'required' : 'nullable',
                'date',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'O nome completo é obrigatório.',
            'name.min'            => 'O nome deve ter no mínimo 3 caracteres.',
            'email.unique'        => 'Este e-mail já está em uso por outro cliente.',
            'password.required'   => 'Crie uma senha de acesso.',
            'password.min'        => 'A senha deve ter no mínimo 6 caracteres.',
            'phone.required'      => 'O WhatsApp/Telefone é obrigatório.',
            'phone.min'           => 'Forneça um número de telefone válido com DDD.',
            'cpf.required'        => 'O CPF é obrigatório para cadastro no aplicativo.',
            'cpf.unique'          => 'Este CPF já está cadastrado em outra conta.',
            'birth_date.required' => 'A data de nascimento é obrigatória.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
            ]);
        }

        if ($this->has('cpf')) {
            $cleanCpf = preg_replace('/\D/', '', (string) $this->input('cpf'));
            $this->merge([
                'cpf' => !empty($cleanCpf) ? $cleanCpf : null,
            ]);
        }
    }
}
