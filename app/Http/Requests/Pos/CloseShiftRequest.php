<?php

namespace App\Http\Requests\Pos;

use App\Models\CompanySetting;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $setting = CompanySetting::getCached() ?? CompanySetting::first();
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

        // Decodifica caso venha como string JSON
        if (is_string($attendees)) {
            $decoded = json_decode($attendees, true);
            $attendees = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        } elseif (!is_array($attendees)) {
            $attendees = [];
        }

        // Normaliza extraindo apenas o ID inteiro, suportando objetos {id: 1}, {user_id: 1} ou inteiros diretos
        $normalized = [];
        foreach ($attendees as $attendee) {
            if (is_array($attendee)) {
                $id = $attendee['id'] ?? $attendee['user_id'] ?? null;
                if ($id !== null && is_numeric($id)) {
                    $normalized[] = (int) $id;
                }
            } elseif (is_numeric($attendee)) {
                $normalized[] = (int) $attendee;
            }
        }

        $this->merge([
            'attendees' => array_values(array_unique($normalized)),
        ]);
    }
}
