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
            'amount'                        => ['required', 'numeric', 'min:0'],
            'retained_amount'               => ['nullable', 'numeric', 'min:0'],
            'observation'                   => ['nullable', 'string', 'max:500'],
            'pin'                           => ['nullable', 'string'],
            'details'                       => ['nullable'],
            'terminal_settlements'          => ['nullable', 'array'],
            'attendees'                     => [$requireAttendance ? 'required' : 'nullable', 'array', $requireAttendance ? 'min:1' : 'min:0'],
            'attendees.*.user_id'           => ['required', 'integer', 'exists:users,id'],
            'attendees.*.attendance_status' => ['nullable', 'string', 'in:present,absent,vacation,day_off'],
            'attendees.*.daily_rate'        => ['nullable', 'numeric', 'min:0'],
            'attendees.*.assigned_role'     => ['nullable', 'string'],
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
            $attendees = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        } elseif (!is_array($attendees)) {
            $attendees = [];
        }

        $normalized = [];
        foreach ($attendees as $att) {
            if (is_array($att)) {
                $userId = $att['user_id'] ?? $att['id'] ?? null;
                if ($userId !== null && is_numeric($userId)) {
                    $normalized[] = [
                        'user_id'           => (int) $userId,
                        'attendance_status' => $att['attendance_status'] ?? $att['status'] ?? 'present',
                        'daily_rate'        => isset($att['daily_rate']) ? (float) $att['daily_rate'] : (isset($att['rate']) ? (float) $att['rate'] : 0.0),
                        'assigned_role'     => $att['assigned_role'] ?? null,
                    ];
                }
            } elseif (is_numeric($att)) {
                $normalized[] = [
                    'user_id'           => (int) $att,
                    'attendance_status' => 'present',
                    'daily_rate'        => 0.0,
                    'assigned_role'     => null,
                ];
            }
        }

        $this->merge([
            'attendees' => $normalized,
        ]);
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var \App\Services\RestaurantOps\OperationalShiftClosureService $closureService */
            $closureService = app(\App\Services\RestaurantOps\OperationalShiftClosureService::class);
            $blockers = $closureService->getPendingShiftBlockers();

            if ($blockers['has_blockers']) {
                $mensagens = [];

                if (!empty($blockers['unclosed_orders']) && count($blockers['unclosed_orders']) > 0) {
                    $qtd = count($blockers['unclosed_orders']);
                    $exemplos = collect($blockers['unclosed_orders'])->take(3)->map(function ($o) {
                        $ident = $o->table_number ? "Mesa {$o->table_number}" : ($o->customer_name ?: "#{$o->id}");
                        return "{$ident} ({$o->status})";
                    })->implode(', ');
                    $mensagens[] = "Existem {$qtd} pedido(s) em andamento: {$exemplos}. Conclua-os ou cancele-os antes de fechar o caixa.";
                }

                if (!empty($blockers['unpaid_orders']) && count($blockers['unpaid_orders']) > 0) {
                    $qtd = count($blockers['unpaid_orders']);
                    $exemplos = collect($blockers['unpaid_orders'])->take(3)->map(function ($o) {
                        $ident = $o->table_number ? "Mesa {$o->table_number}" : ($o->customer_name ?: "#{$o->id}");
                        return "{$ident} (R$ " . number_format($o->total, 2, ',', '.') . ")";
                    })->implode(', ');
                    $mensagens[] = "Existem {$qtd} pedido(s) com pagamento pendente: {$exemplos}. Registre o recebimento antes de encerrar o turno.";
                }

                foreach ($mensagens as $msg) {
                    $validator->errors()->add('shift_blockers', $msg);
                }
            }
        });
    }
}
