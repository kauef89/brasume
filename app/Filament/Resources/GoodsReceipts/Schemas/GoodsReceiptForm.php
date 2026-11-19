<?php

namespace App\Filament\Resources\GoodsReceipts\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class GoodsReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        $recalculateInternalization = static function (Get $get, Set $set): void {
            $rawQty = (float) ($get('raw_quantity') ?? 0);
            $factor = (float) ($get('conversion_factor') ?: 1);
            if ($factor <= 0) {
                $factor = 1.0;
            }

            $purchaseUnitCost = (float) ($get('purchase_unit_cost') ?? 0);

            $internalQty      = round($rawQty * $factor, 4);
            $internalUnitCost = round($purchaseUnitCost / $factor, 5);

            $set('quantity', $internalQty);
            $set('unit_cost_estimated', $internalUnitCost);
        };

        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        // --- LINHA 1 ---
                        Select::make('supplier_id')
                            ->label('Fornecedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm(\App\Filament\Resources\Suppliers\Schemas\SupplierForm::getQuickCreateSchema())
                            ->createOptionAction(function ($action) {
                                return $action
                                    ->modalHeading('Cadastro Rápido de Fornecedor')
                                    ->modalWidth('md');
                            })
                            ->columnSpan(1),

                        TextInput::make('receipt_number')
                            ->label('Nº do Canhoto / Cupom')
                            ->placeholder('Ex: SHOPEE-1049')
                            ->maxLength(50)
                            ->columnSpan(1),

                        DatePicker::make('received_at')
                            ->label('Data da Compra / Entrada')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        // --- LINHA 2 ---
                        Toggle::make('is_retroactive')
                            ->label('Compra Retroativa (Apenas Histórico)')
                            ->helperText('Não altera o estoque atual nem lança no caixa/financeiro.')
                            ->default(fn () => request()->boolean('retroactive'))
                            ->live()
                            ->afterStateHydrated(function (Set $set, $record) {
                                if ($record && $record->payment_origin === 'retroactive') {
                                    $set('is_retroactive', true);
                                }
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $set('requires_nfe', false);
                                } else {
                                    $set('requires_nfe', true);
                                    $set('payment_origin', 'payable');
                                }
                            })
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('requires_nfe')
                            ->label('Aguardando NF-e Posterior?')
                            ->helperText('Deixe ativo se o fornecedor emitirá uma Nota Fiscal.')
                            ->default(fn () => !request()->boolean('retroactive'))
                            ->visible(fn (Get $get) => !$get('is_retroactive'))
                            ->inline(false)
                            ->columnSpan(1),

                        Select::make('payment_origin')
                            ->label('Forma / Origem do Pagamento')
                            ->options([
                                'cash_drawer'  => 'Pago no Caixa (Sangria)',
                                'bank_account' => 'Pago via Banco (PIX / Cartão)',
                                'partner'      => 'Pago pelo Sócio (Reembolso)',
                                'payable'      => 'A Prazo / Boleto Direto',
                            ])
                            ->default('payable')
                            ->visible(fn (Get $get) => !$get('is_retroactive'))
                            ->required(fn (Get $get) => !$get('is_retroactive'))
                            ->live()
                            ->helperText(fn (Get $get) => match ($get('payment_origin')) {
                                'cash_drawer'  => 'Gera sangria imediata no caixa físico.',
                                'bank_account' => 'Debita diretamente da conta para conciliação.',
                                'partner'      => 'Gera Contas a Pagar em favor do sócio.',
                                'payable'      => 'Gera duplicata direto para o fornecedor.',
                                default        => '',
                            })
                            ->columnSpan(1),

                        Select::make('bank_account_id')
                            ->label('Conta Bancária Origem')
                            ->options(fn () => \App\Models\BankAccount::liquidTreasury()->orderBy('name')->pluck('name', 'id'))
                            ->visible(fn (Get $get) => !$get('is_retroactive') && $get('payment_origin') === 'bank_account')
                            ->required(fn (Get $get) => !$get('is_retroactive') && $get('payment_origin') === 'bank_account')
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        DatePicker::make('due_date')
                            ->label('Data de Vencimento')
                            ->visible(fn (Get $get) => !$get('is_retroactive') && in_array($get('payment_origin'), ['partner', 'payable']))
                            ->required(fn (Get $get) => !$get('is_retroactive') && in_array($get('payment_origin'), ['partner', 'payable']))
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
                    ->label('Itens Comprados (Conversão & Internalização)')
                    ->schema([
                        // LINHA 1 DO ITEM: Produto + Unidade de Compra + Qtd Comprada + Preço Pago
                        Select::make('product_id')
                            ->label('Insumo / Produto (Estoque Interno)')
                            ->allowHtml()
                            ->options(function () {
                                return Product::query()
                                    ->whereIn('type', [
                                        ProductType::INGREDIENT->value,
                                        ProductType::PACKAGING->value,
                                        ProductType::CONSUMABLE->value,
                                        ProductType::RESALE->value,
                                    ])
                                    ->where('status', '!=', ProductStatus::ARCHIVED->value)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(function (Product $product) {
                                        $typeEnum = $product->type instanceof ProductType
                                            ? $product->type
                                            : ProductType::tryFrom((string) $product->type);
                                        $label = $typeEnum ? $typeEnum->getLabel() : 'Insumo';
                                        $uom = strtoupper($product->unit_of_measure ?? 'UN');
                                        $badgeColor = match ($typeEnum?->value) {
                                            ProductType::INGREDIENT->value => 'text-amber-600 bg-amber-50 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800',
                                            ProductType::CONSUMABLE->value => 'text-gray-600 bg-gray-100 border-gray-300 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                                            ProductType::PACKAGING->value  => 'text-sky-600 bg-sky-50 border-sky-200 dark:bg-sky-950/50 dark:text-sky-400 dark:border-sky-800',
                                            ProductType::RESALE->value     => 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800',
                                            default                        => 'text-gray-600 bg-gray-100 border-gray-300',
                                        };
                                        $html = "<div class='flex items-center justify-between gap-2 w-full'><span class='truncate font-medium'>{$product->name}</span><span class='inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold border rounded {$badgeColor} shrink-0'>{$label} • {$uom}</span></div>";
                                        return [$product->id => $html];
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->createOptionForm(\App\Filament\Resources\Products\Schemas\ProductForm::getQuickCreateSchema())
                            ->createOptionUsing(function (array $data) {
                                $data['current_stock'] = 0;
                                $data['status'] = ProductStatus::AVAILABLE->value;
                                return Product::create($data)->id;
                            })
                            ->createOptionAction(function ($action) {
                                return $action
                                    ->modalHeading('Cadastro Rápido de Insumo')
                                    ->modalWidth('md');
                            })
                            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($recalculateInternalization) {
                                if ($state) {
                                    $prod = Product::find($state);
                                    if ($prod) {
                                        $pkg = method_exists($prod, 'resolvePurchasePackaging')
                                            ? $prod->resolvePurchasePackaging()
                                            : ['factor' => 1, 'purchase_uom' => $prod->unit_of_measure];

                                        $factor = (float) ($pkg['factor'] ?: 1);
                                        if ($factor <= 0) {
                                            $factor = 1.0;
                                        }

                                        $rawUom = strtoupper((string) ($pkg['purchase_uom'] ?: $prod->unit_of_measure ?: 'UN'));
                                        $allowedUoms = ['UN', 'GF', 'CX', 'PCT', 'FD', 'LT_LATA', 'KG', 'LT', 'G', 'ML'];
                                        $purchaseUom = in_array($rawUom, $allowedUoms, true) ? $rawUom : 'UN';

                                        $estimatedPackPrice = round(((float) ($prod->base_cost ?: 0)) * $factor, 2);

                                        $set('purchase_uom', $purchaseUom);
                                        $set('conversion_factor', $factor);
                                        $set('purchase_unit_cost', $estimatedPackPrice);
                                    }
                                }

                                $recalculateInternalization($get, $set);
                            })
                            ->columnSpan(5),

                        Select::make('purchase_uom')
                            ->label('Unid. Compra')
                            ->options([
                                'UN'      => 'UN (Unidade)',
                                'GF'      => 'GF (Garrafa)',
                                'CX'      => 'CX (Caixa)',
                                'PCT'     => 'PCT (Pacote)',
                                'FD'      => 'FD (Fardo)',
                                'LT_LATA' => 'Lata / Balde',
                                'KG'      => 'KG (Quilo)',
                                'LT'      => 'LT (Litro)',
                                'G'       => 'G (Grama)',
                                'ML'      => 'ML (Mililitro)',
                            ])
                            ->default('UN')
                            ->required()
                            ->live()
                            ->columnSpan(2),

                        TextInput::make('raw_quantity')
                            ->label('Qtd. Comprada')
                            ->numeric()
                            ->inputMode('decimal')
                            ->default(1)
                            ->required()
                            ->live(debounce: 250)
                            ->afterStateUpdated($recalculateInternalization)
                            ->suffix(fn (Get $get) => str_replace('_LATA', '', (string) ($get('purchase_uom') ?: 'UN')))
                            ->columnSpan(2),

                        TextInput::make('purchase_unit_cost')
                            ->label('Preço Pago (Unid. Compra)')
                            ->numeric()
                            ->inputMode('decimal')
                            ->prefix('R$')
                            ->required()
                            ->dehydrated(false)
                            ->live(debounce: 250)
                            ->afterStateHydrated(function (Set $set, $record) {
                                if ($record) {
                                    $factor = (float) ($record->conversion_factor ?: 1);
                                    if ($factor <= 0) {
                                        $factor = 1.0;
                                    }
                                    $unitCost = (float) ($record->unit_cost_estimated ?? 0);
                                    $set('purchase_unit_cost', round($unitCost * $factor, 4));
                                }
                            })
                            ->afterStateUpdated($recalculateInternalization)
                            ->suffix(fn (Get $get) => '/' . str_replace('_LATA', '', (string) ($get('purchase_uom') ?: 'UN')))
                            ->columnSpan(3),

                        // LINHA 2 DO ITEM: Fator de Conversão + Resumo Visual da Internalização
                        TextInput::make('conversion_factor')
                            ->label('Fator de Conversão')
                            ->numeric()
                            ->inputMode('decimal')
                            ->default(1)
                            ->required()
                            ->live(debounce: 250)
                            ->afterStateUpdated($recalculateInternalization)
                            ->helperText(function (Get $get) {
                                $prodId = $get('product_id');
                                $internalUom = $prodId ? strtoupper((string) Product::where('id', $prodId)->value('unit_of_measure')) : 'UN';
                                $buyUom = str_replace('_LATA', '', (string) ($get('purchase_uom') ?: 'UN'));
                                $factor = (float) ($get('conversion_factor') ?: 1);
                                $fmtFactor = rtrim(rtrim(number_format($factor, 4, ',', '.'), '0'), ',');
                                return "1 {$buyUom} = {$fmtFactor} {$internalUom}";
                            })
                            ->columnSpan(3),

                        TextInput::make('quantity')
                            ->label('Qtd. Internalizada')
                            ->numeric()
                            ->default(1)
                            ->readOnly()
                            ->dehydrated()
                            ->dehydrateStateUsing(function ($state, Get $get) {
                                $raw = (float) ($get('raw_quantity') ?? 0);
                                $factor = (float) ($get('conversion_factor') ?: 1);
                                return round($raw * ($factor > 0 ? $factor : 1.0), 4);
                            })
                            ->suffix(function (Get $get) {
                                $prodId = $get('product_id');
                                return $prodId ? strtoupper((string) Product::where('id', $prodId)->value('unit_of_measure')) : 'UN';
                            })
                            ->columnSpan(3),

                        TextInput::make('unit_cost_estimated')
                            ->label('Custo Unit. Internalizado')
                            ->numeric()
                            ->prefix('R$')
                            ->readOnly()
                            ->dehydrated()
                            ->dehydrateStateUsing(function ($state, Get $get) {
                                $purchasePrice = (float) ($get('purchase_unit_cost') ?? 0);
                                $factor = (float) ($get('conversion_factor') ?: 1);
                                if ($factor <= 0) {
                                    $factor = 1.0;
                                }
                                return $purchasePrice > 0
                                    ? round($purchasePrice / $factor, 5)
                                    : round((float) $state, 5);
                            })
                            ->suffix(function (Get $get) {
                                $prodId = $get('product_id');
                                $uom = $prodId ? strtoupper((string) Product::where('id', $prodId)->value('unit_of_measure')) : 'UN';
                                return "/{$uom}";
                            })
                            ->columnSpan(3),

                        Placeholder::make('item_summary')
                            ->label('Total do Item')
                            ->content(function (Get $get) {
                                $rawQty = (float) ($get('raw_quantity') ?? 0);
                                $purchasePrice = (float) ($get('purchase_unit_cost') ?? 0);
                                $total = round($rawQty * $purchasePrice, 2);
                                $fmtTotal = number_format($total, 2, ',', '.');
                                return new HtmlString("<div class='pt-2 text-sm font-bold text-emerald-600 dark:text-emerald-400'>R$ {$fmtTotal}</div>");
                            })
                            ->columnSpan(3),
                    ])
                    ->columns(12)
                    ->defaultItems(1)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Observações do Recebimento')
                    ->placeholder('Ex: Compra retroativa Shopee para composição de histórico de custo.')
                    ->columnSpanFull(),
            ]);
    }
}
