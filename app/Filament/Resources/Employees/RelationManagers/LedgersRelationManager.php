<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema; // 🚀 Importação v4
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;

class LedgersRelationManager extends RelationManager
{
    // O nome da relação no Model Employee
    protected static string $relationship = "ledgers";

    protected static ?string $title = "Conta Corrente (Extrato)";

    // 🚀 Assinatura 100% atualizada para o Filament v4
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("description")
            ->columns([
                Tables\Columns\TextColumn::make("created_at")
                    ->label("Data")
                    ->dateTime("d/m/Y H:i")
                    ->sortable(),

                Tables\Columns\TextColumn::make("type")
                    ->label("Tipo")
                    ->badge()
                    ->formatStateUsing(
                        fn(string $state): string => match ($state) {
                            "credit" => "Serviço Prestado (+)",
                            "debit" => "Pagamento/Vale (-)",
                        },
                    )
                    ->color(
                        fn(string $state): string => match ($state) {
                            "credit" => "success",
                            "debit" => "danger",
                        },
                    ),

                Tables\Columns\TextColumn::make("description")
                    ->label("Descrição")
                    ->searchable(),

                Tables\Columns\TextColumn::make("amount")
                    ->label("Valor")
                    ->money("BRL")
                    ->color(
                        fn($record) => $record->type === "credit"
                            ? "success"
                            : "danger",
                    )
                    ->weight("bold"),

                Tables\Columns\TextColumn::make("balance")
                    ->label("Saldo Atualizado")
                    ->money("BRL")
                    ->weight("bold")
                    ->color(fn($state) => $state < 0 ? "danger" : "primary"),
            ])
            ->defaultSort("created_at", "desc")
            ->filters([
                //
            ])
            ->headerActions([
                // ==========================================
                // BOTÃO 1: LANÇAR SERVIÇO (CRÉDITO)
                // ==========================================
                Action::make("add_credit")
                    ->label("Lançar Serviço")
                    ->icon("heroicon-m-plus-circle")
                    ->color("success")
                    ->form([
                        Forms\Components\Select::make("service_type")
                            ->label("Tipo de Serviço")
                            ->options([
                                "diaria" => "Diária Padrão",
                                "outro" => "Outro Serviço / Avulso",
                            ])
                            ->live()
                            ->default("diaria")
                            ->afterStateUpdated(function (
                                $get,
                                $set,
                                $livewire,
                            ) {
                                if ($get("service_type") === "diaria") {
                                    $employee = $livewire->getOwnerRecord();
                                    $set(
                                        "amount",
                                        $employee->base_remuneration ?? 0,
                                    );
                                    $set("description", "Diária de Trabalho");
                                } else {
                                    $set("amount", null);
                                    $set("description", null);
                                }
                            }),

                        Forms\Components\TextInput::make("description")
                            ->label("Descrição")
                            ->required(),

                        Forms\Components\TextInput::make("amount")
                            ->label('Valor a Pagar (R$)')
                            ->numeric()
                            ->required()
                            ->prefix('R$'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $employee = $livewire->getOwnerRecord();

                        // Descobre o saldo atual da última movimentação
                        $latestLedger = $employee
                            ->ledgers()
                            ->latest("id")
                            ->first();
                        $currentBalance = $latestLedger
                            ? (float) $latestLedger->balance
                            : 0;

                        // Cria o crédito e atualiza o saldo (Soma)
                        $employee->ledgers()->create([
                            "type" => "credit",
                            "description" => $data["description"],
                            "amount" => $data["amount"],
                            "balance" =>
                                $currentBalance + (float) $data["amount"],
                        ]);
                    }),

                // ==========================================
                // BOTÃO 2: LANÇAR VALE/PAGAMENTO (DÉBITO)
                // ==========================================
                Action::make("add_debit")
                    ->label("Lançar Adiantamento / Vale")
                    ->icon("heroicon-m-minus-circle")
                    ->color("danger")
                    ->form([
                        Forms\Components\TextInput::make("description")
                            ->label("Descrição")
                            ->default("Vale / Adiantamento Manual")
                            ->required(),

                        Forms\Components\TextInput::make("amount")
                            ->label('Valor (R$)')
                            ->numeric()
                            ->required()
                            ->prefix('R$'),
                    ])
                    ->action(function (array $data, $livewire) {
                        $employee = $livewire->getOwnerRecord();

                        // Descobre o saldo atual da última movimentação
                        $latestLedger = $employee
                            ->ledgers()
                            ->latest("id")
                            ->first();
                        $currentBalance = $latestLedger
                            ? (float) $latestLedger->balance
                            : 0;

                        // Cria o débito e atualiza o saldo (Subtrai)
                        $employee->ledgers()->create([
                            "type" => "debit",
                            "description" => $data["description"],
                            "amount" => $data["amount"],
                            "balance" =>
                                $currentBalance - (float) $data["amount"],
                        ]);
                    }),
            ])
            ->actions([
                // Protegido contra edições manuais para garantir a integridade
            ]);
    }
}
