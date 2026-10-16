<?php

namespace App\Filament\Resources\Printers\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class PrinterTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Apelido')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_cashier')
                    ->label('Caixa Principal')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-minus')
                    ->alignCenter(),

                TextColumn::make('connection_type')
                    ->label('Conexão')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'usb' => 'USB',
                        'network' => 'Rede Local (IP)',
                        'bluetooth' => 'Bluetooth',
                        default => $state
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'usb' => 'info',
                        'network' => 'success',
                        'bluetooth' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('os_name')
                    ->label('Endereço / Porta')
                    ->limit(20)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 20 ? $state : null;
                    }),

                ToggleColumn::make('is_active')
                    ->label('Ativa'),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Editar')
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()])
            ]);
    }
}
