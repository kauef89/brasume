<?php
namespace App\Filament\Resources\Orders\Tables;

use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Models\Product;
use Illuminate\Support\HtmlString;

class OrderTable {
    public static function configure(Table $table): Table {
        return $table->columns([
            Tables\Columns\TextColumn::make('id')->label('Pedido #')->sortable()->searchable(),

            Tables\Columns\TextColumn::make('customer_name')->label('Cliente / Mesa')
                ->state(function ($record) {
                    if ($record->customer) return $record->customer->name;
                    if ($record->customer_name) return $record->customer_name;
                    if ($record->table_number) return 'Mesa: ' . $record->table_number;
                    return 'Não Identificado';
                })
                ->searchable(),

            Tables\Columns\TextColumn::make('type')->label('Modalidade')->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'delivery' => 'Delivery',
                    'counter' => 'Balcão',
                    'pickup' => 'Retirada',
                    'table' => 'Mesa',
                    default => $state
                })
                ->colors([
                    'info' => 'delivery',
                    'warning' => 'counter',
                    'success' => 'pickup',
                    'gray' => 'table'
                ]),

            Tables\Columns\TextColumn::make('total')->label('Total')->money('BRL')->sortable(),

            Tables\Columns\TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'Pendente',
                    'preparing' => 'Preparando',
                    'ready' => 'Pronto',
                    'out_for_delivery' => 'Em Entrega',
                    'closed' => 'Concluído',
                    'cancelled' => 'Cancelado',
                    default => $state
                })
                ->colors([
                    'warning' => 'pending',
                    'info' => 'preparing',
                    'success' => fn ($state) => in_array($state, ['ready', 'closed']),
                    'danger' => 'cancelled',
                    'primary' => 'out_for_delivery',
                ]),

            Tables\Columns\TextColumn::make('payment_status')->label('Pagamento')->badge()
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'paid' => 'Pago',
                    'partial' => 'Parcial',
                    'pending' => 'Pendente',
                    'cancelled' => 'Cancelado',
                    default => $state
                })
                ->colors([
                    'success' => 'paid',
                    'warning' => fn ($state) => in_array($state, ['pending', 'partial']),
                    'danger' => 'cancelled'
                ]),

            Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
        ])->defaultSort('created_at', 'desc')
        ->actions([
            EditAction::make()->iconButton(),

            // ==========================================
            // 🚀 BOTÕES FISCAIS (Ajustado com Danfce para Bobinas Térmicas)
            // ==========================================
            ActionGroup::make([
                // 1. Download Nativo do XML
                Action::make('download_xml')
                    ->label('Baixar XML')
                    ->icon('heroicon-o-code-bracket')
                    ->color('info')
                    ->action(function ($record) {
                        $doc = $record->fiscalDocument;
                        return response()->streamDownload(function () use ($doc) {
                            echo $doc->xml_content;
                        }, "{$doc->access_key}.xml");
                    }),

                // 2. Visualização do DANFE NFC-e usando Danfce 🚀
                Action::make('view_danfe')
                    ->label('Ver Cupom (DANFE)')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->modalHeading(fn ($record) => "NFC-e Autorizada - Pedido #{$record->id}")
                    ->modalWidth('2xl') // Reduzido de 7xl para 2xl pois cupom térmico é estreito e fica lindo compacto!
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function ($record) {
                        try {
                            // 🚀 CORREÇÃO DEFINITIVA: Instancia a classe Danfce para o Modelo 65
                            $danfce = new \NFePHP\DA\NFe\Danfce($record->fiscalDocument->xml_content);
                            $danfce->creditsIntegratorFooter("Gerado por Brasume ERP");
                            $pdf = $danfce->render();
                            $base64 = base64_encode($pdf);
                            return new HtmlString("<iframe src='data:application/pdf;base64,{$base64}#toolbar=0' width='100%' height='650px' style='border: none; border-radius: 8px;'></iframe>");
                        } catch (\Exception $e) {
                            return new HtmlString("<div class='p-4 text-red-500'>Erro ao gerar PDF do Cupom: {$e->getMessage()}</div>");
                        }
                    }),
            ])
            ->label('Fiscal')
            ->icon('heroicon-m-document-check')
            ->color('gray')
            ->tooltip('Ações Fiscais (NFC-e)')
            ->visible(fn ($record) => $record->fiscalDocument && filled($record->fiscalDocument->xml_content)),

            // ==========================================
            // BOTÃO DA INTELIGÊNCIA DE EXPEDIÇÃO
            // ==========================================
            Action::make('despachar')
                ->label('Despachar')
                ->icon('heroicon-m-truck')
                ->color('success')
                ->hidden(fn ($record) => in_array($record->status, ['out_for_delivery', 'closed', 'cancelled']) || in_array($record->type, ['table', 'counter']))
                ->fillForm(function ($record): array {
                    $totalPoints = 0;

                    if ($record->items) {
                        foreach ($record->items as $item) {
                            $pontosDoProduto = $item->product->packaging_points ?? 0;
                            $totalPoints += ($pontosDoProduto * $item->quantity);
                        }
                    }

                    $sacolaIdeal = Product::whereIn('type', ['packaging', 'supply'])
                        ->where('packaging_points', '>=', $totalPoints)
                        ->where('packaging_points', '>', 0)
                        ->orderBy('packaging_points', 'asc')
                        ->first();

                    return [
                        'packaging_product_id' => $sacolaIdeal ? $sacolaIdeal->id : null,
                        'packaging_qty' => $sacolaIdeal ? 1 : 0,
                    ];
                })
                ->form([
                    Select::make('packaging_product_id')
                        ->label('Embalagem Sugerida')
                        ->options(Product::whereIn('type', ['packaging', 'supply'])->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->helperText('O sistema calculou o volume deste pedido e sugeriu a embalagem ideal.'),

                    TextInput::make('packaging_qty')
                        ->label('Quantidade Utilizada')
                        ->numeric()
                        ->default(1)
                        ->required(),
                ])
                ->modalSubmitActionLabel('Confirmar Expedição')
                ->action(function (array $data, $record): void {
                    $sacola = Product::find($data['packaging_product_id']);

                    if ($sacola && $sacola->track_inventory) {
                        $sacola->current_stock -= $data['packaging_qty'];
                        $sacola->save();
                    }

                    $record->status = 'out_for_delivery';
                    $record->save();

                    Notification::make()
                        ->title('Pedido Despachado!')
                        ->body('Estoque de embalagem baixado e pedido a caminho.')
                        ->success()
                        ->send();
                }),
        ])
        ->bulkActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ]);
    }
}
