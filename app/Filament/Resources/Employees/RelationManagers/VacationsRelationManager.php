<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\EmployeeVacation;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VacationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vacations';
    protected static ?string $title = 'Férias & Afastamentos';
    protected static ?string $modelLabel = 'Período de Férias';
    protected static ?string $pluralModelLabel = 'Férias & Afastamentos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getFormComponents(): array
    {
        return [
            DatePicker::make('start_date')
                ->label('Início das Férias')
                ->required(),

            DatePicker::make('end_date')
                ->label('Término das Férias')
                ->afterOrEqual('start_date')
                ->required(),

            Textarea::make('description')
                ->label('Período Aquisitivo / Observações')
                ->placeholder('Ex: Férias do período aquisitivo 2024/2025. 15 dias restantes.')
                ->rows(3),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('start_date')
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('start_date')
                    ->label('Data Inicial')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('end_date')
                    ->label('Data Final')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('duration')
                    ->label('Duração')
                    ->state(function (EmployeeVacation $record) {
                        if (!$record->start_date || !$record->end_date) return '-';
                        $days = $record->start_date->diffInDays($record->end_date) + 1;
                        return "{$days} dias";
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->state(function (EmployeeVacation $record) {
                        $today = now()->startOfDay();
                        if ($record->start_date && $record->end_date) {
                            if ($today->between($record->start_date, $record->end_date)) {
                                return 'Em Gozo (Ativo)';
                            }
                            if ($today->lt($record->start_date)) {
                                return 'Agendada (Futuro)';
                            }
                            return 'Concluída';
                        }
                        return 'N/D';
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Em Gozo (Ativo)'   => 'warning',
                        'Agendada (Futuro)' => 'info',
                        'Concluída'         => 'success',
                        default             => 'gray',
                    }),

                TextColumn::make('description')
                    ->label('Observação / Período Aquisitivo')
                    ->wrap()
                    ->placeholder('Sem observações')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('start_date')
                    ->form([
                        DatePicker::make('from')->label('Início a partir de'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('start_date', '<=', $date))
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agendar Férias')
                    ->form(self::getFormComponents()),
            ])
            ->actions([
                EditAction::make()->form(self::getFormComponents()),
                DeleteAction::make(),
            ]);
    }
}