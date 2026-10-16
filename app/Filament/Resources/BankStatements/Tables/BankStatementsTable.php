<?php

namespace App\Filament\Resources\BankStatements\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\HtmlString;
use App\Models\BankStatement;
use App\Models\Supplier;
use App\Models\Employee;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use App\Filament\Resources\BankStatements\Actions\BulkPersonalAction;
use App\Filament\Resources\BankStatements\Actions\BulkExpenseAction;
use App\Filament\Resources\BankStatements\Actions\BulkEmployeeAction;
use App\Filament\Resources\BankStatements\Actions\BulkRecurringAction;
use App\Filament\Resources\BankStatements\Actions\BulkTransferAction;
use Filament\Notifications\Notification;

class BankStatementsTable
{
    public static function build(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make("bank_date")
                    ->label("Data")
                    ->date("d/m/Y")
                    ->sortable(),

                Tables\Columns\TextColumn::make("movement_type")
                    ->label("Operação")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "pix_in" => "Pix Recebido", "pix_out" => "Pix Pago", "boleto_out" => "Boleto",
                            "card_in" => "Cartão", "transfer" => "Transferência", "fixed_bill" => "Conta Fixa",
                            "tax_bill" => "Imposto", "payroll" => "Folha Pagto.", "bank_fee" => "Taxa Banco",
                            "atm_in" => "Depósito", "atm_out" => "Saque", default => "Outros",
                        },
                    )
                    ->color(fn(string $state): string => match ($state) {
                        "pix_in", "card_in", "atm_in" => "success",
                        "bank_fee" => "danger",
                        default => "warning",
                    }),

                Tables\Columns\TextColumn::make("counterparty_name")
                    ->label("Contraparte")
                    ->searchable()
                    ->weight("bold")
                    ->description(function (BankStatement $record) {
                        $badge = "";
                        if ($record->document) {
                            if (Supplier::where("document", $record->document)->exists()) {
                                $badge = '<span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800">🏢 Fornecedor</span>';
                            } elseif (Employee::where("cpf", $record->document)->exists()) {
                                $badge = '<span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-fuchsia-100 text-fuchsia-800">👤 Colaborador</span>';
                            }
                        }
                        return $badge ? new HtmlString($badge) : null;
                    }),

                Tables\Columns\TextColumn::make("document")
                    ->label("Documento / ID")
                    ->searchable()
                    ->getStateUsing(fn (BankStatement $record) => $record->document ?: '-')
                    ->description(fn (BankStatement $record) => $record->statement_identifier ? "ID: " . $record->statement_identifier : null),

                Tables\Columns\TextColumn::make("amount")
                    ->label("Valor")
                    ->money("BRL")
                    ->sortable()
                    ->color(fn($record) => $record->type === "credit" ? "success" : "danger")
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("status")
                    ->label("Status")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "pending" => "Pendente", "reconciled" => "Conciliado", "ignored" => "Ignorado", default => $state,
                        }
                    )
                    ->colors(["warning" => "pending", "success" => "reconciled", "gray" => "ignored"]),
            ])
            ->filters([]) // Filtros removidos pois as Abas assumiram este papel
            ->actions([
            Action::make("conciliar")
                ->label("Conciliar")
                ->icon("heroicon-m-arrows-right-left")
                ->button()
                ->outlined()
                ->color("gray")
                ->visible(fn(BankStatement $record): bool => $record->status === "pending")
                ->modalHeading("Conciliar Transação")
                // 🚀 Abre elegantemente na lateral
                ->slideOver()
                ->fillForm(function (BankStatement $record): array {
                    $cleanDoc = preg_replace('/[^0-9]/', '', $record->document);
                    $supplierId = null;

                    if (!empty($cleanDoc)) {
                        $supplier = \App\Models\Supplier::where('document', $record->document)
                            ->orWhere('document', $cleanDoc)
                            ->first();

                        if (!$supplier) {
                            $supplier = \App\Models\Supplier::whereNotNull('document')->get()->first(function ($s) use ($cleanDoc) {
                                return preg_replace('/[^0-9]/', '', $s->document) === $cleanDoc;
                            });
                        }
                        $supplierId = $supplier?->id;
                    }

                    $amount = abs((float) $record->amount);
                    $date = $record->bank_date->format('Y-m-d');
                    $desc = $record->counterparty_name ?: $record->description;
                    $locked = $supplierId !== null;

                    return [
                        'conciliation_type' => null,
                        'action_mode' => null,
                        'existing_payable_id' => null,
                        'existing_fixed_id' => null,
                        'existing_personal_id' => null,

                        'avulsa_new' => [
                            'amount' => $amount,

                            // 🚀 O SEGREDO DO PAGAMENTO AQUI:
                            'due_date' => $date, // Mantemos como padrão inicial caso o utilizador não queira alterar
                            'payment_date' => $date, // A data do banco vai para a Data de Pagamento!
                            'status' => 'paid', // Forçamos o status para Pago
                            'is_status_locked' => true, // Injetamos a trava antifraude/anticlique!

                            'description' => $desc,
                            'supplier_id' => $supplierId,
                            'is_supplier_locked' => $locked,
                        ],
                        'fixa_new' => [
                            'default_amount' => $amount,
                            'due_day' => $record->bank_date->format('d'),
                            'name' => $desc,
                            'supplier_id' => $supplierId,
                            'is_supplier_locked' => $locked,
                        ],
                        'pessoal_new' => [
                            'description' => '',
                            'supplier_id' => $supplierId,
                            'is_supplier_locked' => $locked,
                        ],
                    ];
                })
                ->form(fn(BankStatement $record) => [ConciliationTabsSchema::make($record)])
                ->action(function (array $data, BankStatement $record): void {
                    ConciliationTabsSchema::processConciliation($data, $record);

                    \Filament\Notifications\Notification::make()
                        ->title("Conciliado com sucesso!")
                        ->success()
                        ->send();
                }),

           //     Action::make("ignorar")
           //         ->label("Ignorar")
           //         ->icon("heroicon-m-x-mark")
           //         ->button()
           //         ->color("gray")
           //         ->requiresConfirmation()
           //         ->visible(fn(BankStatement $record): bool => $record->status === "pending")
           //         ->action(fn(BankStatement $record) => $record->update(["status" => "ignored"])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkExpenseAction::make(),
                    BulkEmployeeAction::make(), // 🚀 A Nova
                    BulkTransferAction::make(),
                    BulkRecurringAction::make(), // 🚀 A Nova
                    BulkPersonalAction::make(),
                ]),
            ])
            ->defaultSort("bank_date", "desc");
    }
}
