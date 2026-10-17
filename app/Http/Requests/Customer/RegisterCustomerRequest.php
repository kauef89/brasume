<?php

namespace App\Http\Requests\Customer;

use App\Models\CompanySetting;
use Carbon\Carbon;
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $setting = CompanySetting::getCached() ?? CompanySetting::first();
            $birthDate = $this->input('birth_date');

            // Regra: Bloquear menores se allow_minors for false
            if ($birthDate && !($setting?->allow_minors ?? true)) {
                try {
                    $age = Carbon::parse($birthDate)->age;
                    if ($age < 18) {
                        $validator->errors()->add('birth_date', 'É necessário ter pelo menos 18 anos para se cadastrar nesta loja.');
                    }
                } catch (\Exception $e) {}
            }
        });
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
