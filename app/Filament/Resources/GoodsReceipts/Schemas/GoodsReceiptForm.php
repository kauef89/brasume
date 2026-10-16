<?php

namespace App\Filament\Resources\GoodsReceipts\Schemas;

use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 🚀 Único Grid de 3 colunas para o topo inteiro, forçando ocupar 100% da largura
                Grid::make(3)
                    ->schema([
                        // --- LINHA 1 ---
                        Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            // 🚀 QUICK CREATE: Fornecedor
                            ->createOptionForm(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getQuickCreateSchema())
                            ->createOptionAction(function ($action) {
                                return $action
                                    ->modalHeading('Cadastro Rápido de Fornecedor')
                                    ->modalWidth('md');
                            })
                            ->columnSpan(1),

                        TextInput::make('receipt_number')
                            ->label('Nº do Canhoto / Cupom')
                            ->placeholder('Ex: CANHOTO-1049')
                            ->maxLength(50)
                            ->columnSpan(1),

                        DatePicker::make('received_at')
                            ->label('Data de Entrada')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        // --- LINHA 2 ---
                        // DIMENSÃO FISCAL
                        \Filament\Forms\Components\Toggle::make('requires_nfe')
                            ->label('Aguardando NF-e Posterior?')
                            ->helperText('Deixe ativo se o fornecedor emitirá uma Nota Fiscal.')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),

                        // DIMENSÃO FINANCEIRA
                        Select::make('payment_origin')
                            ->label('Forma / Origem do Pagamento')
                            ->options([
                                'cash_drawer'     => 'Pago no Caixa (Sangria)',
                                'bank_account'    => 'Pago via Banco (PIX / Cartão)',
                                'partner'         => 'Pago pelo Sócio (Reembolso)',
                                'payable'         => 'A Prazo / Boleto Direto',
                            ])
                            ->default('payable')
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get) => match ($get('payment_origin')) {
                                'cash_drawer'     => 'Gera sangria imediata no caixa físico.',
                                'bank_account'    => 'Debita diretamente da conta para conciliação.',
                                'partner'         => 'Gera Contas a Pagar em favor do sócio.',
                                'payable'         => 'Gera duplicata direto para o fornecedor.',
                                default           => '',
                            })
                            ->columnSpan(1),

                        // COMPLEMENTOS (Ocupam a 3ª coluna da Linha 2 dinamicamente)
                        Select::make('bank_account_id')
                            ->label('Conta Bancária Origem')
                            ->options(fn () => \App\Models\BankAccount::where('is_active', true)->pluck('name', 'id'))
                            ->visible(fn (Get $get) => $get('payment_origin') === 'bank_account')
                            ->required(fn (Get $get) => $get('payment_origin') === 'bank_account')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        DatePicker::make('due_date')
                            ->label('Data de Vencimento')
                            ->visible(fn (Get $get) => in_array($get('payment_origin'), ['partner', 'payable']))
                            ->required(fn (Get $get) => in_array($get('payment_origin'), ['partner', 'payable']))
                            ->default(now()->addDays(7))
                            ->columnSpan(1),
                    ])
                    ->columnSpanFull(),

                FileUpload::make('document_path')
                    ->label('Foto do Canhoto / Cupom / Recibo Físico')
                    ->image()
                    ->directory('goods-receipts')
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->relationship('items')
                    ->label('Insumos Recebidos')
                    ->schema([
                        Select::make('product_id')
                            ->label('Insumo / Produto')
                            // 🚀 Mudamos de options() para relationship() para habilitar a criação nativa do Filament
                            ->relationship('product', 'name', fn ($query) => $query->where('track_inventory', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // 🚀 QUICK CREATE: Produto/Insumo
                            ->createOptionForm(\App\Filament\Resources\Products\Schemas\ProductForm::getQuickCreateSchema())
                            ->createOptionAction(function ($action) {
                                return $action
                                    ->modalHeading('Cadastro Rápido de Insumo')
                                    ->modalWidth('md');
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $prod = Product::find($state);
                                    if ($prod) {
                                        $set('unit_cost_estimated', $prod->base_cost ?: 0);
                                        if (method_exists($prod, 'resolvePurchasePackaging')) {
                                            $pkg = $prod->resolvePurchasePackaging();
                                            $set('conversion_factor', $pkg['factor'] ?: 1);
                                            $set('purchase_uom', $pkg['purchase_uom'] ?: $prod->unit_of_measure);
                                        } else {
                                            $set('conversion_factor', 1);
                                            $set('purchase_uom', $prod->unit_of_measure);
                                        }
                                    }
                                }
                            })
                            ->columnSpan(3),

                        TextInput::make('raw_quantity')
                            ->label('Qtd. Comprada')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(debounce: 400)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $raw = (float) ($get('raw_quantity') ?: 1);
                                $factor = (float) ($get('conversion_factor') ?: 1);
                                $set('quantity', round($raw * $factor, 4));
                            })
                            ->suffix(fn (Get $get) => $get('purchase_uom') ?: 'UN')
                            ->columnSpan(2),

                        TextInput::make('conversion_factor')
                            ->label('Fator (Unid./Fardo)')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->live(debounce: 400)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $raw = (float) ($get('raw_quantity') ?: 1);
                                $factor = (float) ($get('conversion_factor') ?: 1);
                                $set('quantity', round($raw * $factor, 4));
                            })
                            ->columnSpan(1),

                        TextInput::make('quantity')
                            ->label('Entrada Real')
                            ->numeric()
                            ->readOnly()
                            ->suffix(function (Get $get) {
                                $prodId = $get('product_id');
                                return $prodId ? Product::find($prodId)?->unit_of_measure : 'UN';
                            })
                            ->columnSpan(2),

                        TextInput::make('unit_cost_estimated')
                            ->label('Custo Unit. Real')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->columnSpan(2),
                    ])
                    ->columns(10)
                    ->defaultItems(1)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Observações do Recebimento')
                    ->placeholder('Ex: Mercadoria recebida no turno da tarde. Aguardando nota fiscal no fim do mês.')
                    ->columnSpanFull(),
            ]);
    }
}
