<?php

namespace App\Filament\Resources\AccountReceivables\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components as SchemaComponents;
use Filament\Forms\Components as FormComponents;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class AccountReceivableForm
{
    public static function getFormSchema(): array
    {
        return [
            SchemaComponents\Tabs::make('ReceivablesTabs')->tabs([

                SchemaComponents\Tabs\Tab::make('Dados do Recebível')->schema([
                    SchemaComponents\Grid::make(3)->schema([
                        FormComponents\TextInput::make('description')->label('Descrição da Venda')->required()->columnSpan(2),
                        FormComponents\TextInput::make('payment_method')->label('Forma de Pagamento')->columnSpan(1),
                        FormComponents\TextInput::make('installment')->label('Parcela (Ex: 1/1)')->default('1/1')->columnSpan(1),
                        FormComponents\Select::make('bank_account_id')->relationship('bankAccount', 'name')->label('Conta de Destino (Banco/Caixa)')->searchable()->preload()->required()->columnSpan(1),

                        FormComponents\Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->label('Cliente (Opcional)')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(\App\Filament\Resources\Customers\Schemas\CustomerForm::getFormSchema()) // <-- FASE 3
                            ->columnSpan(1),

                        FormComponents\Select::make('order_id')->relationship('order', 'id')->label('Pedido Vinculado (Opcional)')->searchable()->getOptionLabelFromRecordUsing(fn ($record) => "Pedido #{$record->id}")->columnSpan(1),
                    ]),
                ]),

                SchemaComponents\Tabs\Tab::make('Valores e Prazos')->schema([
                    SchemaComponents\Grid::make(3)->schema([
                        FormComponents\TextInput::make('gross_amount')->label('Valor Bruto (Passado na Máquina)')->numeric()->prefix('R$')->required()->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('net_amount', round((float)$get('gross_amount') - (float)$get('fee_amount'), 2));
                            }),
                        FormComponents\TextInput::make('fee_amount')->label('Taxa Descontada')->numeric()->prefix('R$')->default(0)->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('net_amount', round((float)$get('gross_amount') - (float)$get('fee_amount'), 2));
                            }),
                        FormComponents\TextInput::make('net_amount')->label('Valor Líquido (A Receber)')->numeric()->prefix('R$')->required()->readOnly()->extraInputAttributes(['style' => 'font-weight: bold; background-color: #f3f4f6;']),
                        FormComponents\DatePicker::make('due_date')->label('Previsão de Recebimento')->required(),
                        FormComponents\Select::make('status')->label('Status')->options(['pending' => 'Aguardando Pagamento', 'paid' => 'Recebido no Banco', 'anticipated' => 'Antecipado', 'cancelled' => 'Cancelado'])->default('pending')->live()->required(),
                        FormComponents\DatePicker::make('payment_date')->label('Data em que Caiu na Conta')->visible(fn (Get $get) => in_array($get('status'), ['paid', 'anticipated'])),
                    ]),
                ]),

            ])->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }
}
