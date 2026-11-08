<?php

namespace App\Filament\Resources\ChecklistLogs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ChecklistLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Executado em')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('checklistTask.checklist.title')
                    ->label('Rotina / Grupo')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('checklistTask.description')
                    ->label('Tarefa Executada')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('user.name')
                    ->label('Colaborador')
                    ->searchable()
                    ->placeholder('Sistema / Automático')
                    ->sortable(),

                TextColumn::make('cashShift.id')
                    ->label('Turno')
                    ->formatStateUsing(fn ($state) => $state ? "#{$state}" : '-')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed', 'done', 'ok' => 'success',
                        'pending', 'waiting'      => 'warning',
                        'skipped', 'missed'       => 'danger',
                        default                   => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'completed', 'done', 'ok' => 'Concluída',
                        'pending', 'waiting'      => 'Pendente',
                        'skipped', 'missed'       => 'Não Cumprida / Pulada',
                        default                   => ucfirst($state ?? 'Concluída'),
                    }),

                TextColumn::make('notes')
                    ->label('Observações / Evidências')
                    ->searchable()
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'completed' => 'Concluída',
                        'pending'   => 'Pendente',
                        'skipped'   => 'Não Cumprida',
                    ]),

                SelectFilter::make('user_id')
                    ->label('Colaborador')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('Executado De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
                    ),
            ]);
    }
}