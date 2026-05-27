<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use App\Models\AccountPayable;
use App\Models\PaymentBatch;
use App\Models\FixedAccount;
use App\Models\BankStatement;
use App\Models\Transaction;
use App\Filament\Resources\AccountPayables\Schemas\AccountPayableForm;
use App\Filament\Resources\FixedAccounts\Schemas\FixedAccountForm;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;

class ConciliationTabsSchema
{
    public static function make(BankStatement $statement): Tabs
    {
        return Tabs::make("ConciliationTabs")
            ->tabs([
                // 🏢 ABA 1: DESPESAS AVULSAS E LOTES
                Tab::make("Despesa Avulsa / Lote")
                    ->icon("heroicon-m-document-plus")
                    ->schema([
                        Section::make(
                            "1. Vincular a uma conta ou lote existente",
                        )
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Select::make("existing_payable_id")
                                    ->label("Selecione a Despesa Pendente")
                                    ->placeholder(
                                        "Busque por fornecedor, lote ou valor...",
                                    )
                                    ->searchable()
                                    // 🚀 AQUI ESTÁ A BELEZA VISUAL RESTAURADA DO AUTOCONCILIATE!
                                    ->options(function () {
                                        $payables = AccountPayable::where(
                                            "status",
                                            "pending",
                                        )
                                            ->whereNull("payment_batch_id")
                                            ->get()
                                            ->mapWithKeys(
                                                fn($p) => [
                                                    "payable_" . $p->id =>
                                                        "📄 " .
                                                        ($p->supplier->name ??
                                                            ($p->employee
                                                                ->name ??
                                                                "S/F")) .
                                                        " - R$ " .
                                                        number_format(
                                                            $p->amount,
                                                            2,
                                                            ",",
                                                            ".",
                                                        ),
                                                ],
                                            );

                                        $batches = PaymentBatch::where(
                                            "status",
                                            "pending",
                                        )
                                            ->get()
                                            ->mapWithKeys(
                                                fn($b) => [
                                                    "batch_" . $b->id =>
                                                        "📦 LOTE: " .
                                                        $b->description .
                                                        " - R$ " .
                                                        number_format(
                                                            $b->total_amount,
                                                            2,
                                                            ",",
                                                            ".",
                                                        ),
                                                ],
                                            );

                                        return collect($payables)
                                            ->union($batches)
                                            ->toArray();
                                    }),
                            ]),

                        Section::make("2. Ou lançar nova Despesa / Pagamento")
                            ->description("Utilizando o formulário oficial")
                            ->schema(
                                // Volta a usar o Form padrão (com ou sem o isModal, como preferir)
                                AccountPayableForm::getFormSchema(),
                            ),
                    ]),

                // 🔄 ABA 2: CONTAS FIXAS
                Tab::make("Conta Fixa")
                    ->icon("heroicon-m-arrow-path")
                    ->schema([
                        Section::make(
                            "1. Vincular a uma Assinatura/Conta Fixa existente",
                        )
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Select::make("existing_fixed_id")
                                    ->label("Selecione a Conta Fixa")
                                    ->options(
                                        FixedAccount::where("is_active", 1)
                                            ->get()
                                            // 🚀 EMOJIS NA CONTA FIXA TAMBÉM!
                                            ->mapWithKeys(
                                                fn($f) => [
                                                    $f->id =>
                                                        "🔄 " .
                                                        ($f->supplier->name ??
                                                            "S/F") .
                                                        " - " .
                                                        $f->name,
                                                ],
                                            ),
                                    )
                                    ->searchable(),
                            ]),

                        Section::make(
                            "2. Ou cadastrar nova Assinatura/Conta Fixa",
                        )->schema(FixedAccountForm::getFormSchema()),
                    ]),
            ])
            ->columnSpanFull();
    }

    public static function processConciliation(
        array $data,
        BankStatement $statement,
    ): void {
        $payable = null;
        $batch = null;

        // 1. Lógica para Despesa Avulsa ou LOTE
        if (!empty($data["existing_payable_id"])) {
            $idRaw = $data["existing_payable_id"];

            // 🚀 LÊ OS PREFIXOS DO SELECT RICO PARA SABER SE É LOTE OU DESPESA
            if (str_starts_with($idRaw, "batch_")) {
                $batchId = str_replace("batch_", "", $idRaw);
                $batch = PaymentBatch::find($batchId);
            } else {
                $payableId = str_replace("payable_", "", $idRaw);
                $payable = AccountPayable::find($payableId);

                // Fallback de segurança
                if (!$payable) {
                    $payable = AccountPayable::find($idRaw);
                }
            }
        } elseif (!empty($data["amount"])) {
            // Criou nova despesa avulsa no modal
            $payable = AccountPayable::create([
                ...$data,
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
        }

        // 2. Lógica para Conta Fixa
        if (!empty($data["existing_fixed_id"])) {
            $fixed = FixedAccount::find($data["existing_fixed_id"]);
            $payable = AccountPayable::create([
                "description" =>
                    $fixed->name .
                    " (" .
                    $statement->bank_date->format("M/Y") .
                    ")",
                "amount" => abs((float) $statement->amount),
                "supplier_id" => $fixed->supplier_id,
                "accounting_category" => $fixed->accounting_category,
                "accounting_subcategory" => $fixed->accounting_subcategory,
                "due_date" => $statement->bank_date,
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
        } elseif (!empty($data["name"]) && !empty($data["value_type"])) {
            // Criou nova conta fixa no modal
            $fixed = FixedAccount::create($data);
            $payable = AccountPayable::create([
                "description" => $fixed->name . " (Inicial)",
                "amount" => abs((float) $statement->amount),
                "supplier_id" => $fixed->supplier_id,
                "accounting_category" => $fixed->accounting_category,
                "accounting_subcategory" => $fixed->accounting_subcategory,
                "due_date" => $statement->bank_date,
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
        }

        // 3. REGISTRAR A TRANSAÇÃO E CONCILIAR
        $transaction = Transaction::create([
            "type" => $statement->type,
            "amount" => abs((float) $statement->amount),
            "description" => $statement->description,
            "transaction_date" => clone $statement->bank_date,
            "supplier_id" => $payable ? $payable->supplier_id : null,
        ]);

        if ($batch) {
            $statement->update([
                "conciliable_id" => $batch->id,
                "conciliable_type" => PaymentBatch::class,
                "transaction_id" => $transaction->id,
                "status" => "reconciled",
            ]);
            $batch->update([
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
            AccountPayable::where("payment_batch_id", $batch->id)->update([
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
        } elseif ($payable) {
            $statement->update([
                "conciliable_id" => $payable->id,
                "conciliable_type" => AccountPayable::class,
                "transaction_id" => $transaction->id,
                "status" => "reconciled",
            ]);
            $payable->update([
                "status" => "paid",
                "payment_date" => $statement->bank_date,
            ]);
        }
    }
}
