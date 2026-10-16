<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\BulkAction; // <--- A CLASSE CERTA DO BOTÃO
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use App\Models\ProductCostHistory;

class ProcessedStockTable extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms;
    use InteractsWithTable;
    use InteractsWithActions;

    public ?string $invoiceKey = null;

    #[On('items-processed')]
    public function refreshTable(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ProductCostHistory::query()
                    ->with('product')
                    ->where('nfe_key', $this->invoiceKey ?? 'none')
            )
            ->heading('Estoque Comercial')
            ->description('Selecione os itens que deseja estornar e devolver para a fila acima para correção.')
            ->columns([
                TextColumn::make('xml_name')
                    ->label('Produto na Nota')
                    ->searchable(),

                TextColumn::make('original_quantity')
                    ->label('Qtd / U.M.')
                    ->formatStateUsing(fn($state, $record) => str_replace('.', ',', (string)(float)$state) . ' ' . ($record->original_unit_of_measure ?? 'UN')),

                TextColumn::make('original_unit_cost')
                    ->label('Custo Unit.')
                    ->money('BRL'),

                TextColumn::make('product.name')
                    ->label('Produto no Sistema'),

                TextColumn::make('quantity')
                    ->label('Estoque Adicionado')
                    ->formatStateUsing(fn($state, $record) => str_replace('.', ',', (string)(float)$state) . ' ' . ($record->product->unit_of_measure ?? '')),
            ])
            ->actions([
                Action::make('undoStock')
                    ->label('Desfazer')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $itemsToRestore = [[
                            'xml_name' => $record->xml_name,
                            'xml_qty' => $record->original_quantity,
                            'xml_cost' => $record->original_unit_cost,
                            'xml_uom' => $record->original_unit_of_measure ?? 'UN',
                            'conversion_factor' => $record->conversion_factor,
                            'product_id' => $record->product_id,
                        ]];

                        if ($record->product) {
                            $record->product->decrement('current_stock', $record->quantity);
                        }
                        $record->delete();

                        $this->dispatch('items-restored', items: $itemsToRestore, isPersonal: false);
                        Notification::make()->title('Item estornado!')->success()->send();
                    })
            ])
            // ✅ AQUI MUDOU O NOME DO MÉTODO PARA TOOLBAR ACTIONS
            ->toolbarActions([
                // ✅ MAS A CLASSE DO BOTÃO CONTINUA SENDO BULKACTION
                BulkAction::make('undoStockBulk')
                    ->label('Estornar Selecionados')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $itemsToRestore = [];

                        foreach ($records as $record) {
                            $itemsToRestore[] = [
                                'xml_name' => $record->xml_name,
                                'xml_qty' => $record->original_quantity,
                                'xml_cost' => $record->original_unit_cost,
                                'xml_uom' => $record->original_unit_of_measure ?? 'UN',
                                'conversion_factor' => $record->conversion_factor,
                                'product_id' => $record->product_id,
                            ];

                            if ($record->product) {
                                $record->product->decrement('current_stock', $record->quantity);
                            }
                            $record->delete();
                        }

                        $this->dispatch('items-restored', items: $itemsToRestore, isPersonal: false);
                        Notification::make()->title('Lote estornado!')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion()
            ]);
    }

    public function render()
    {
        return view('livewire.processed-stock-table');
    }
}
