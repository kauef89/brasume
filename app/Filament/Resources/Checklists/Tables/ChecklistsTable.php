<?php

namespace App\Filament\Resources\Checklists\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class ChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Nome do Grupo (Rotina)')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                // 🚀 Conta magicamente quantas tarefas (checklist_tasks) estão atreladas a este grupo
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('Qtd. de Tarefas')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Permite ligar/desligar a rotina inteira direto da tabela, sem precisar abrir para editar!
                ToggleColumn::make('is_active')
                    ->label('Rotina Ativa')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Todos os Grupos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
