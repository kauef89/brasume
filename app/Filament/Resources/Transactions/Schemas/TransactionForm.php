<?php

namespace App\Filament\Resources\Transactions\Schemas;


use App\Enums\TransactionType;use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Illuminate\Support\Facades\Auth;

class TransactionForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Section::make("Detalhes da Movimentação")->schema([
                SchemaComponents\Grid::make(2)->schema([
                    FormComponents\Select::make("type")
                        ->label("Tipo de Movimento")
                        ->options(TransactionType::class)
                        ->required()
                        ->live()
                        ->columnSpan(1),
                    FormComponents\TextInput::make("amount")
                        ->label("Valor")
                        ->numeric()
                        ->step(0.01)
                        ->prefix('R$')
                        ->required()
                        ->columnSpan(1),
                    FormComponents\DateTimePicker::make("transaction_date")
                        ->label("Data e Hora")
                        ->default(now())
                        ->required()
                        ->columnSpan(1),
                    FormComponents\TextInput::make("operator_name")
                        ->label("Operador")
                        ->default(fn() => Auth::user()?->name ?? "Sistema")
                        ->columnSpan(1),
                    FormComponents\TextInput::make("description")
                        ->label("Descrição / Motivo")
                        ->placeholder(
                            "Ex: Pagamento Fornecedor, Sangria de Caixa, etc.",
                        )
                        ->required()
                        ->columnSpan(2),
                ]),
            ]),

            SchemaComponents\Section::make("Vínculos Financeiros (Conciliação)")
                ->schema([
                    SchemaComponents\Grid::make(2)->schema([
                        FormComponents\Select::make("bank_account_id")
                            ->relationship("bankAccount", "name")
                            ->label("Conta Bancária / Cofre")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        FormComponents\Select::make("payment_method_id")
                            ->relationship("paymentMethod", "name")
                            ->label("Forma de Pagamento (Opcional)")
                            ->searchable()
                            ->preload()
                            ->createOptionForm(
                                \App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm::getFormSchema(),
                            ) // <-- FASE 3
                            ->columnSpan(1),

                        FormComponents\Select::make("order_id")
                            ->relationship("order", "id")
                            ->label("ID do Pedido Vinculado (Opcional)")
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(
                                fn(
                                    $record,
                                ) => "Pedido #{$record->id} - {$record->customer_name}",
                            )
                            ->columnSpan(2),
                    ]),
                ])
                ->collapsed(),
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
