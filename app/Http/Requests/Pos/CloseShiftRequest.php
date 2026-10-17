<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $setting = \App\Models\CompanySetting::getCached() ?? \App\Models\CompanySetting::first();
        $requireAttendance = (bool) ($setting?->require_shift_attendance ?? false);

        return [
            'amount'          => ['required', 'numeric', 'min:0'],
            'retained_amount' => ['nullable', 'numeric', 'min:0'],
            'observation'     => ['nullable', 'string', 'max:500'],
            'pin'             => ['nullable', 'string'],
            'details'         => ['nullable'],
            'attendees'       => [$requireAttendance ? 'required' : 'nullable', 'array', $requireAttendance ? 'min:1' : 'min:0'],
            'attendees.*'     => ['integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendees.required' => 'É obrigatório selecionar os colaboradores escalados para fechar o turno.',
            'attendees.min'      => 'Vincule ao menos um colaborador ativo no turno.',
        ];
    }

    public function prepareForValidation(): void
    {
        $attendees = $this->input('attendees');

        if (is_string($attendees)) {
            $decoded = json_decode($attendees, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['attendees' => $decoded]);
            } else {
                $this->merge(['attendees' => []]);
            }
        } elseif (!is_array($attendees)) {
            $this->merge(['attendees' => []]);
        }
    }
}
