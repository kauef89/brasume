<?php

namespace App\Livewire;

use App\Models\EmployeeLedger;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\HtmlString; // <-- Importamos o HtmlString aqui!

class EmployeeStatement extends BaseWidget
{
    public $record;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Calculamos o saldo antes para usar no HTML do título
        $currentBalance = EmployeeLedger::where('employee_id', $this->record->id)->latest('created_at')->value('balance') ?? 0;

        // Definimos a cor (verde se for positivo, vermelho se for negativo)
        $colorClass = $currentBalance >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400';
        $formattedBalance = number_format((float)$currentBalance, 2, ',', '.');

        return $table
            ->query(
                EmployeeLedger::query()->where('employee_id', $this->record->id)
            )
            // Aqui fazemos a mágica do Flexbox para colocar um de cada lado!
            ->heading(new HtmlString("
                <div class='flex w-full items-center justify-between'>
                    <span>Extrato Financeiro</span>
                    <span class='text-sm  text-gray-500 dark:text-gray-400'>
                        Saldo Atual: <strong class='text-xl {$colorClass}'>R$ {$formattedBalance}</strong>
                    </span>
                </div>
            "))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data / Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Valor')
                    ->badge()
                    ->color(fn($record) => $record->type === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(fn($record) => ($record->type === 'credit' ? '+ R$ ' : '- R$ ') . number_format((float)$record->amount, 2, ',', '.')),

                TextColumn::make('balance')
                    ->label('Saldo Histórico')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->prefix('R$ ')
                    ->color(fn($record) => $record->balance >= 0 ? 'success' : 'danger')
                    ->weight('bold'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
