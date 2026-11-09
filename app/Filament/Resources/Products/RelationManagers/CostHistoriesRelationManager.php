<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductCostHistory;
use App\Services\Purchases\CostRectificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CostHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'costHistories';

    protected static ?string $title = 'Histórico de Compras';

    protected static ?string $modelLabel = 'Compra / Custo';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('xml_name')
                ->label('Produto')
                ->required(),
            TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->required(),
            TextInput::make('unit_cost')
                ->label('Custo Unitário')
                ->numeric()
                ->prefix('R$')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        $service = app(CostRectificationService::class);

        return $table
            ->recordTitleAttribute('xml_name')
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('xml_name')
                    ->label('Produto')
                    ->searchable()
                    ->description(fn (ProductCostHistory $record) => $record->nfe_key ? 'NF-e: ' . substr($record->nfe_key, 25, 9) : null),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qtd.')
                    ->formatStateUsing(function (ProductCostHistory $record) {
                        $origQty = $record->original_quantity ?: $record->quantity;
                        $origUom = strtolower($record->original_unit_of_measure ?: 'un');
                        return number_format((float) $origQty, 2, ',', '.') . ' ' . $origUom;
                    }),

                Tables\Columns\TextColumn::make('original_unit_cost')
                    ->label('Custo')
                    ->formatStateUsing(function (ProductCostHistory $record) {
                        $cost = $record->original_unit_cost ?: ($record->unit_cost * ($record->conversion_factor ?: 1));
                        $uom = strtolower($record->original_unit_of_measure ?: 'un');
                        return 'R$ ' . number_format((float) $cost, 2, ',', '.') . ' /' . $uom;
                    }),

                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo Unit.')
                    ->formatStateUsing(function (ProductCostHistory $record) {
                        $prodUom = strtolower($record->product?->unit_of_measure ?? 'un');
                        return 'R$ ' . number_format((float) $record->unit_cost, 4, ',', '.') . ' /' . $prodUom;
                    })
                    ->weight('bold')
                    ->color(fn (ProductCostHistory $record) => $service->isSuspect($record) ? 'danger' : 'success'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar Compra Manual'),
            ])
            ->actions([
                Action::make('retificar')
                    ->label('Retificar')
                    ->icon(fn (ProductCostHistory $record) => $service->isSuspect($record) ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-wrench-screwdriver')
                    ->color(fn (ProductCostHistory $record) => $service->isSuspect($record) ? 'warning' : 'gray')
                    ->tooltip(fn (ProductCostHistory $record) => $service->isSuspect($record)
                        ? 'Atenção: inconsistência detectada no fator de conversão ou custo!'
                        : 'Retificar parâmetros de conversão desta compra'
                    )
                    ->modalHeading(fn (ProductCostHistory $record) => "Retificar Compra: {$record->xml_name}")
                    ->modalDescription('Corrija o fator de conversão de compras importadas com erro de caixa ou unidade.')
                    ->modalWidth('lg')
                    ->fillForm(fn (ProductCostHistory $record) => [
                        'conversion_factor' => (float) ($record->conversion_factor ?: 1),
                        'adjust_inventory'  => true,
                    ])
                    ->form(function (ProductCostHistory $record) use ($service) {
                        $preview = $service->calculatePreview($record, (float) ($record->conversion_factor ?: 1));
                        $uom = $record->original_unit_of_measure ?: 'un';
                        $prodUom = $record->product?->unit_of_measure ?: 'UN';

                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('orig_info')
                                    ->label('Dado Original da NF-e')
                                    ->content(new HtmlString("<strong>{$preview['original_quantity']} {$uom}</strong> a <strong>R$ " . number_format($preview['original_unit_cost'], 2, ',', '.') . " /{$uom}</strong>")),

                                Placeholder::make('current_state')
                                    ->label('Lançamento Atual no Sistema')
                                    ->content(new HtmlString("Fator atual: <strong>{$record->conversion_factor}</strong><br>Custo unit.: <span class='text-rose-600 font-bold'>R$ " . number_format((float) $record->unit_cost, 4, ',', '.') . " /{$prodUom}</span>")),
                            ]),

                            TextInput::make('conversion_factor')
                                ->label('Novo Fator de Conversão')
                                ->helperText("Quantos(as) {$prodUom} vêm em 1 {$uom}? (Ex: 1 cx = 12 lt -> digite 12. Se comprou unidade avulsa -> digite 1)")
                                ->numeric()
                                ->required()
                                ->live(debounce: 300),

                            Placeholder::make('preview')
                                ->label('Resultado Após Correção')
                                ->content(function (Get $get) use ($record, $service, $prodUom) {
                                    $factor = (float) ($get('conversion_factor') ?: 1);
                                    $calc = $service->calculatePreview($record, $factor);

                                    return new HtmlString("
                                        <div class='p-3 bg-gray-50 dark:bg-gray-800 rounded-lg text-xs space-y-1 border border-gray-200 dark:border-gray-700'>
                                            <div>📦 Nova Quantidade de Entrada: <strong class='text-primary-600 font-bold'>" . number_format($calc['new_quantity'], 3, ',', '.') . " {$prodUom}</strong></div>
                                            <div>💰 Novo Custo Unitário: <strong class='text-emerald-600 font-bold'>R$ " . number_format($calc['new_unit_cost'], 4, ',', '.') . " /{$prodUom}</strong></div>
                                        </div>
                                    ");
                                }),

                            Toggle::make('adjust_inventory')
                                ->label('Compensar Diferença no Estoque Físico?')
                                ->helperText('Ajusta a sobra ou falta decorrente do erro no saldo atual do produto.')
                                ->default(true),
                        ];
                    })
                    ->action(function (array $data, ProductCostHistory $record) use ($service) {
                        $factor = (float) ($data['conversion_factor'] ?: 1);
                        $adjustStock = !empty($data['adjust_inventory']);

                        $result = $service->rectify($record, $factor, $adjustStock);

                        Notification::make()
                            ->title('Histórico de Custo Retificado!')
                            ->body('Custo unitário ajustado para R$ ' . number_format($result['new_unit_cost'], 4, ',', '.') . ' e estoque sincronizado.')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
