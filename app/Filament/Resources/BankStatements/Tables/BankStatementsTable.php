<?php

namespace App\Filament\Resources\BankStatements\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use App\Models\BankStatement;
use App\Models\Supplier;
use App\Models\Employee;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema; // A Mágica!
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
                            "pix_in" => "Pix Recebido",
                            "pix_out" => "Pix Pago",
                            "boleto_out" => "Boleto",
                            "card_in" => "Cartão",
                            "transfer" => "Transferência",
                            "fixed_bill" => "Conta Fixa",
                            "tax_bill" => "Imposto",
                            "payroll" => "Folha Pagto.",
                            "bank_fee" => "Taxa Banco",
                            "atm_in" => "Depósito",
                            "atm_out" => "Saque",
                            default => "Outros",
                        },
                    )
                    ->color(
                        fn(string $state): string => match ($state) {
                            "pix_in", "card_in", "atm_in" => "success",
                            "bank_fee" => "danger",
                            default => "warning",
                        },
                    ),
                Tables\Columns\TextColumn::make("counterparty_name")
                    ->label("Contraparte")
                    ->searchable()
                    ->weight("bold")
                    ->description(function (BankStatement $record) {
                        $subText = $record->document
                            ? "Doc: " . $record->document
                            : ($record->statement_identifier
                                ? "ID: " . $record->statement_identifier
                                : "");
                        $badge = "";
                        if ($record->document) {
                            if (
                                Supplier::where(
                                    "document",
                                    $record->document,
                                )->exists()
                            ) {
                                $badge =
                                    '<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">🏢 Fornecedor</span>';
                            } elseif (
                                \Illuminate\Support\Facades\Schema::hasColumn(
                                    "employees",
                                    "cpf",
                                ) &&
                                Employee::where(
                                    "cpf",
                                    $record->document,
                                )->exists()
                            ) {
                                $badge =
                                    '<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-fuchsia-100 text-fuchsia-800">👤 Colaborador</span>';
                            }
                        }
                        return $subText
                            ? new HtmlString(
                                "<div class='flex items-center mt-1'><span class='text-xs text-gray-500'>{$subText}</span>{$badge}</div>",
                            )
                            : null;
                    }),
                Tables\Columns\TextColumn::make("amount")
                    ->label("Valor")
                    ->money("BRL")
                    ->sortable()
                    ->color(
                        fn($record) => $record->type === "credit"
                            ? "success"
                            : "danger",
                    )
                    ->weight("bold"),
                Tables\Columns\TextColumn::make("status")
                    ->label("Status")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "pending" => "Pendente",
                            "reconciled" => "Conciliado",
                            "ignored" => "Ignorado",
                            default => $state,
                        },
                    )
                    ->colors([
                        "warning" => "pending",
                        "success" => "reconciled",
                        "gray" => "ignored",
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make("status")
                    ->options([
                        "pending" => "Pendentes",
                        "reconciled" => "Conciliados",
                        "ignored" => "Ignorados",
                    ])
                    ->default("pending"),
                Tables\Filters\SelectFilter::make("type")->options([
                    "debit" => "Saídas",
                    "credit" => "Entradas",
                ]),
            ])
            ->actions([
                // AQUI CHAMAMOS NOSSO COMPONENTE MESTRE
                Action::make("conciliar")
                    ->label("Conciliar")
                    ->icon("heroicon-m-arrows-right-left")
                    ->button()
                    ->outlined()
                    ->color("gray")
                    ->visible(
                        fn(BankStatement $record): bool => $record->status ===
                            "pending",
                    )
                    ->modalHeading("Conciliar Transação Manualmente")
                    ->modalWidth("4xl")
                    ->form(
                        fn(BankStatement $record) => [
                            ConciliationTabsSchema::make($record),
                        ],
                    )
                    ->action(function (
                        array $data,
                        BankStatement $record,
                    ): void {
                        // Repassamos os dados do formulário para nossa classe mestre salvar
                        ConciliationTabsSchema::processConciliation(
                            $data,
                            $record,
                        );
                        Notification::make()
                            ->title("Conciliado com sucesso!")
                            ->success()
                            ->send();
                    }),

                Action::make("ignorar")
                    ->label("Ignorar")
                    ->icon("heroicon-m-x-mark")
                    ->button()
                    ->color("gray")
                    ->requiresConfirmation()
                    ->visible(
                        fn(BankStatement $record): bool => $record->status ===
                            "pending",
                    )
                    ->action(
                        fn(BankStatement $record) => $record->update([
                            "status" => "ignored",
                        ]),
                    ),
            ])
            ->defaultSort("bank_date", "desc");
    }
}
