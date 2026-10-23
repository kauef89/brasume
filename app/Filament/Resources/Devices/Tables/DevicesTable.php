<?php

namespace App\Filament\Resources\Devices\Tables;

use App\Enums\DeviceType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DevicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Dispositivo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Função')
                    ->badge(),

                TextColumn::make('terminal_code')
                    ->label('Nº Terminal (Banco)')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Código copiado!')
                    ->placeholder('Não configurado')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('assignment_scope')
                    ->label('Destinação')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'counter'  => 'Balcão',
                        'delivery' => 'Delivery',
                        'waiter'   => 'Salão',
                        'floating' => 'Flutuante',
                        default    => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'counter'  => 'success',
                        'delivery' => 'warning',
                        'waiter'   => 'info',
                        default    => 'gray',
                    }),

                TextColumn::make('device_uuid')
                    ->label('UUID')
                    ->fontFamily('mono')
                    ->limit(8)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->device_uuid),

                ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->sortable(),

                TextColumn::make('last_active_at')
                    ->label('Último Acesso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Nunca'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Terminal')
                    ->options(DeviceType::class),

                SelectFilter::make('assignment_scope')
                    ->label('Destinação')
                    ->options([
                        'counter'  => 'Balcão',
                        'delivery' => 'Delivery',
                        'waiter'   => 'Salão',
                        'floating' => 'Flutuante',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Autorização')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
