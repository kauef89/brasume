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
use App\Models\PersonalPurchase;

class ProcessedPersonalTable extends Component implements HasForms, HasTable, HasActions
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
                PersonalPurchase::query()
                    ->where('nfe_key', $this->invoiceKey ?? 'none')
            )
            ->heading('Uso Pessoal')
            ->description('Selecione os itens que deseja estornar e devolver para a fila acima para correção.')
            ->columns([
                TextColumn::make('xml_name')
                    ->label('Produto na Nota')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Qtd')
                    ->formatStateUsing(fn($state) => str_replace('.', ',', (string)(float)$state) . ' UN'),

                TextColumn::make('unit_cost')
                    ->label('Custo Unit.')
                    ->money('BRL'),

                TextColumn::make('total_cost')
                    ->label('Valor Total')
                    ->money('BRL')
                    ->badge()
                    ->color('warning'),
            ])
            ->actions([
                Action::make('undoPersonal')
                    ->label('Desfazer')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $itemsToRestore = [[
                            'xml_name' => $record->xml_name,
                            'xml_qty' => $record->quantity,
                            'xml_cost' => $record->unit_cost,
                            'xml_uom' => 'UN',
                            'conversion_factor' => 1,
                            'product_id' => null,
                        ]];

                        $record->delete();

                        $this->dispatch('items-restored', items: $itemsToRestore, isPersonal: true);
                        Notification::make()->title('Despesa pessoal estornada!')->success()->send();
                    })
            ])
            // ✅ AQUI MUDOU O NOME DO MÉTODO PARA TOOLBAR ACTIONS
            ->toolbarActions([
                // ✅ MAS A CLASSE DO BOTÃO CONTINUA SENDO BULKACTION
                BulkAction::make('undoPersonalBulk')
                    ->label('Estornar Selecionadas')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Collection $records) {
                        $itemsToRestore = [];

                        foreach ($records as $record) {
                            $itemsToRestore[] = [
                                'xml_name' => $record->xml_name,
                                'xml_qty' => $record->quantity,
                                'xml_cost' => $record->unit_cost,
                                'xml_uom' => 'UN',
                                'conversion_factor' => 1,
                                'product_id' => null,
                            ];
                            $record->delete();
                        }

                        $this->dispatch('items-restored', items: $itemsToRestore, isPersonal: true);
                        Notification::make()->title('Estorno pessoal concluído!')->success()->send();
                    })
                    ->deselectRecordsAfterCompletion()
            ]);
    }

    public function render()
    {
        return view('livewire.processed-personal-table');
    }
}
