<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'min:0'],
            'observation' => ['nullable', 'string', 'max:500'],
            'details'     => ['nullable'],
            'attendees'   => ['nullable', 'array'],
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
