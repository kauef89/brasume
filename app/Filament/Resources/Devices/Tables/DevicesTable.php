<?php

namespace App\Filament\Resources\Devices\Tables;


use App\Enums\DeviceType;use Filament\Actions\BulkActionGroup;
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
                    ->label('Nome do Dispositivo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Tipo / Função')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pos' => '💻 Caixa (PDV)',
                        'kds' => '🍳 Cozinha (KDS)',
                        'totem' => '📱 Totem',
                        'waiter' => '🧑‍🍳 Garçom',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pos' => 'success',
                        'kds' => 'warning',
                        'totem' => 'info',
                        'waiter' => 'primary',
                        default => 'gray',
                    }),

                // 🚀 MÁGICA DE UX: Clique para copiar!
                TextColumn::make('device_uuid')
                    ->label('Chave de Emparelhamento (UUID)')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyableState(fn ($record) => $record->device_uuid)
                    ->copyMessage('chave copiada com sucesso!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->limit(12) // Mostra só o início para não alargar a tabela
                    ->tooltip(fn ($record) => 'Clique para copiar: ' . $record->device_uuid),

                // Permite ligar/desligar a máquina direto da listagem
                ToggleColumn::make('is_active')
                    ->label('Autorizado')
                    ->sortable(),

                TextColumn::make('last_active_at')
                    ->label('Último Acesso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Nunca')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Filtrar por Tipo')
                    ->options(DeviceType::class),
                TernaryFilter::make('is_active')
                    ->label('Status de Acesso')
                    ->boolean()
                    ->trueLabel('Apenas Autorizados')
                    ->falseLabel('Bloqueados'),
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
