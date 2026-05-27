<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions;

class UserTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Utilizador')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label('Cargos')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('pin')
                    ->label('Acesso ao PDV')
                    ->formatStateUsing(fn ($state) => $state ? 'PIN Configurado' : 'Sem PIN')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
