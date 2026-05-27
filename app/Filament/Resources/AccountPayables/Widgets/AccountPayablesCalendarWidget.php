<?php

namespace App\Filament\Resources\AccountPayables\Widgets;

use App\Models\AccountPayable;
use App\Models\Supplier;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use App\Filament\Resources\AccountPayables\AccountPayableResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Actions\Action; // <-- NOVA IMPORTAÇÃO AQUI!

class AccountPayablesCalendarWidget extends FullCalendarWidget
{
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

    // 1. ENSINAMOS O CALENDÁRIO A LER O NOSSO ID CUSTOMIZADO COM A ASSINATURA EXATA
    public function resolveRecord(
        string|int $key,
    ): \Illuminate\Database\Eloquent\Model {
        if (is_string($key) && str_starts_with($key, "payable-")) {
            // Usa o findOrFail para garantir que devolve o Modelo (se não achar, dá erro 404 seguro)
            return AccountPayable::findOrFail(
                str_replace("payable-", "", $key),
            );
        }

        // Se por algum acaso bizarro tentar resolver outra coisa, interrompe de forma segura
        abort(404);
    }

    // 2. SUBSTITUÍMOS O MODAL POR UM REDIRECIONAMENTO DIRETO
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
            ->where("due_date", ">=", $fetchInfo["start"])
            ->where("due_date", "<=", $fetchInfo["end"])
            ->get();

        $supplierIds = $payables->pluck("supplier_id")->filter()->unique();
        $suppliers = Supplier::whereIn("id", $supplierIds)->get()->keyBy("id");

        $individualAccounts = $payables->map(function (
            AccountPayable $payable,
        ) use ($suppliers) {
            $supplier = $suppliers->get($payable->supplier_id);
            $rawSupplierName = $supplier->name ?? "S/ Fornec.";
            $supplierName = Str::limit($rawSupplierName, 12, "...");

            $value = number_format((float) $payable->amount, 2, ",", ".");

            $className = "event-warning";
            if ($payable->status === "paid") {
                $className = "event-success";
            } elseif (
                $payable->status === "pending" &&
                $payable->due_date < now()->startOfDay()
            ) {
                $className = "event-danger";
            }

            return [
                "id" => "payable-" . $payable->id,
                "title" => "{$supplierName} | R$ {$value}",
                "start" => $payable->due_date,
                // Mantemos a URL aqui por segurança, mas o viewAction acima é quem vai mandar no clique agora
                "url" => AccountPayableResource::getUrl("edit", [
                    "record" => $payable->id,
                ]),
                "className" => $className,
                "allDay" => true,
                "sortOrder" => 1,
            ];
        });

        $dailySummaries = AccountPayable::query()
            ->where("due_date", ">=", $fetchInfo["start"])
            ->where("due_date", "<=", $fetchInfo["end"])
            ->select("due_date", DB::raw("SUM(amount) as total_amount"))
            ->groupBy("due_date")
            ->get()
            ->map(function ($summary) {
                $total = number_format(
                    (float) $summary->total_amount,
                    2,
                    ",",
                    ".",
                );

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
