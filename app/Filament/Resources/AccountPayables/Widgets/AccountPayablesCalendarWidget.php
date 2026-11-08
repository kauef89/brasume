<?php

namespace App\Filament\Resources\AccountPayables\Widgets;

use App\Models\AccountPayable;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Filament\Resources\AccountPayables\AccountPayableResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Actions\Action;

class AccountPayablesCalendarWidget extends FullCalendarWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function headerActions(): array
    {
        return [];
    }

    public function config(): array
    {
        return [
            "eventOrder" => "sortOrder",
        ];
    }

    public function resolveRecord(string|int $key): \Illuminate\Database\Eloquent\Model
    {
        if (is_string($key) && str_starts_with($key, "payable-")) {
            return AccountPayable::findOrFail(str_replace("payable-", "", $key));
        }

        abort(404);
    }

    protected function viewAction(): Action
    {
        return Action::make("view")->url(
            fn($record) => $record
                ? AccountPayableResource::getUrl("edit", ["record" => $record])
                : null,
        );
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $payables = AccountPayable::query()
            ->with(['supplier', 'employee', 'recurringExpense', 'bankAccount'])
            ->where("due_date", ">=", $fetchInfo["start"])
            ->where("due_date", "<=", $fetchInfo["end"])
            ->where("amount", ">", 0)
            ->get();

        $individualAccounts = $payables->map(function (AccountPayable $payable) {
            $creditorType = 'Despesa';
            if ($payable->supplier) {
                $rawName = $payable->supplier->name;
                $creditorType = 'Fornecedor';
            } elseif ($payable->employee) {
                $rawName = $payable->employee->name;
                $creditorType = 'Colaborador';
            } elseif ($payable->recurringExpense) {
                $rawName = $payable->recurringExpense->name;
                $creditorType = 'Recorrente';
            } else {
                $rawName = $payable->description;
            }

            $displayName = Str::limit($rawName, 13, "...");
            $value = number_format((float) $payable->amount, 2, ",", ".");

            $className = "event-warning";
            $statusLabel = "Pendente";
            $statusColor = "warning";

            if ($payable->status === "paid") {
                $className = "event-success";
                $statusLabel = "Pago";
                $statusColor = "success";
            } elseif ($payable->status === "pending" && $payable->due_date < now()->startOfDay()) {
                $className = "event-danger";
                $statusLabel = "Atrasado";
                $statusColor = "danger";
            } elseif ($payable->status === "cancelled") {
                $statusLabel = "Cancelado";
                $statusColor = "neutral";
            }

            $categoryFormatted = null;
            if ($payable->accounting_category) {
                $cat = ucfirst($payable->accounting_category);
                $sub = $payable->accounting_subcategory ? ' › ' . ucfirst($payable->accounting_subcategory) : '';
                $categoryFormatted = $cat . $sub;
            }

            return [
                "id" => "payable-" . $payable->id,
                "title" => "{$displayName} | R$ {$value}",
                "start" => $payable->due_date,
                "url" => AccountPayableResource::getUrl("edit", [
                    "record" => $payable->id,
                ]),
                "className" => $className,
                "allDay" => true,
                "sortOrder" => 1,
                "extendedProps" => [
                    "payableId"     => $payable->id,
                    "creditor"      => $rawName,
                    "creditorType"  => $creditorType,
                    "amount"        => "R$ " . $value,
                    "description"   => $payable->description,
                    "dueDate"       => $payable->due_date?->format('d/m/Y'),
                    "paymentDate"   => $payable->payment_date?->format('d/m/Y'),
                    "status"        => $payable->status,
                    "statusLabel"   => $statusLabel,
                    "statusColor"   => $statusColor,
                    "category"      => $categoryFormatted,
                    "recurringName" => $payable->recurringExpense?->name,
                    "bankAccount"   => $payable->bankAccount?->name,
                ],
            ];
        });

        $dailySummaries = AccountPayable::query()
            ->where("due_date", ">=", $fetchInfo["start"])
            ->where("due_date", "<=", $fetchInfo["end"])
            ->where("amount", ">", 0)
            ->select("due_date", DB::raw("SUM(amount) as total_amount"))
            ->groupBy("due_date")
            ->get()
            ->map(function ($summary) {
                $total = number_format((float) $summary->total_amount, 2, ",", ".");

                return [
                    "id" => "summary-" . $summary->due_date,
                    "title" => "R$ {$total}",
                    "start" => $summary->due_date,
                    "className" => "event-daily-summary",
                    "allDay" => true,
                    "sortOrder" => 99,
                ];
            });

        return $individualAccounts->concat($dailySummaries)->toArray();
    }
}