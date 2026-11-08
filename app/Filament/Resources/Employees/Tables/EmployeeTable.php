<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmploymentType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class EmployeeTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('employment_type')
                    ->label('Vínculo')
                    ->badge()
                    ->sortable(),

                TextColumn::make('work_days')
                    ->label('Escala')
                    ->state(function ($record) {
                        $type = $record->employment_type instanceof \App\Enums\EmploymentType ? $record->employment_type->value : (string) $record->employment_type;
                        if (!in_array($type, ['freelance_fixed', 'clt'])) return null;
                        $qtd = count($record->work_days ?? []);
                        return $qtd > 0 ? "{$qtd} dias/sem" : 'Sem escala';
                    })
                    ->badge()
                    ->placeholder('-')
                    ->color('gray'),

                TextColumn::make('user.roles.name')
                    ->label('Cargos / Acesso')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Nenhum cargo'),

                TextColumn::make('user_status')
                    ->label('Conta de Acesso')
                    ->state(fn ($record) => $record->user ? 'Usuário criado' : 'Sem usuário')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Usuário criado' => 'success',
                        'Sem usuário' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'Usuário criado' => 'heroicon-m-link',
                        'Sem usuário' => 'heroicon-m-link-slash',
                        default => 'heroicon-m-link',
                    })
                    ->alignCenter(),

                ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('employment_type')
                    ->label('Tipo de Vínculo')
                    ->options(\App\Enums\EmploymentType::class),
                \Filament\Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->boolean(),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
