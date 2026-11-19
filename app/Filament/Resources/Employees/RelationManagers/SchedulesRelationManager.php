<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\EmployeeSchedule;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';
    protected static ?string $title = 'Escala de Trabalho & Plantões';
    protected static ?string $modelLabel = 'Plantão / Escala';
    protected static ?string $pluralModelLabel = 'Escalas de Trabalho';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getFormComponents(): array
    {
        $roles = [
            'cozinha'     => 'Cozinha',
            'chapa'       => 'Chapeiro',
            'atendimento' => 'Atendimento',
            'caixa'       => 'Caixa / Operação',
            'motoboy'     => 'Entregador / Motoboy',
            'gerente'     => 'Gerente / Líder',
        ];

        if (class_exists(\App\Enums\EmployeeRole::class)) {
            try {
                $enumRoles = [];
                foreach (\App\Enums\EmployeeRole::cases() as $case) {
                    $label = method_exists($case, 'getLabel') ? $case->getLabel() : $case->name;
                    $enumRoles[$case->value] = $label;
                }
                if (!empty($enumRoles)) {
                    $roles = $enumRoles;
                }
            } catch (\Throwable $e) {}
        }

        return [
            DatePicker::make('date')
                ->label('Data da Escala')
                ->required()
                ->default(now()),

            Select::make('shift_type')
                ->label('Regime / Tipo de Turno')
                ->options(\App\Enums\ScheduleShiftType::class)
                ->default(\App\Enums\ScheduleShiftType::REGULAR->value)
                ->required()
                ->live(),

            Select::make('assigned_role')
                ->label('Função / Posto de Trabalho')
                ->options($roles)
                ->visible(fn ($get) => $get('shift_type') !== \App\Enums\ScheduleShiftType::DAY_OFF->value)
                ->searchable(),

            Select::make('status')
                ->label('Situação do Turno')
                ->options([
                    'scheduled' => 'Agendado',
                    'completed' => 'Cumprido',
                    'absent'    => 'Falta / Não Compareceu',
                    'cancelled' => 'Cancelado',
                ])
                ->default('scheduled')
                ->required(),

            Textarea::make('notes')
                ->label('Observações / Trocas')
                ->placeholder('Anotações sobre substituições ou escala')
                ->rows(2),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Data')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';
                        $d = Carbon::parse($state);
                        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                        return $d->format('d/m/Y') . ' (' . ($dias[$d->dayOfWeek] ?? '') . ')';
                    })
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('shift_type')
                    ->label('Regime / Turno')
                    ->badge()
                    ->sortable(),

                TextColumn::make('assigned_role')
                    ->label('Função / Posto')
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state) => ucfirst($state ?? '-')),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed', 'cumprido'  => 'success',
                        'scheduled', 'agendado'  => 'info',
                        'cancelled', 'cancelado' => 'gray',
                        'absent', 'falta'        => 'danger',
                        default                  => 'primary',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'completed', 'cumprido'  => 'Cumprido',
                        'scheduled', 'agendado'  => 'Agendado',
                        'cancelled', 'cancelado' => 'Cancelado',
                        'absent', 'falta'        => 'Falta / Ausente',
                        default                  => ucfirst($state ?? 'Agendado'),
                    }),

                TextColumn::make('notes')
                    ->label('Observações')
                    ->wrap()
                    ->limit(40)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('shift_type')
                    ->label('Regime / Turno')
                    ->options(\App\Enums\ScheduleShiftType::class),

                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'scheduled' => 'Agendado',
                        'completed' => 'Cumprido',
                        'absent'    => 'Falta',
                        'cancelled' => 'Cancelado',
                    ]),

                Filter::make('date')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date))
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Adicionar Escala')
                    ->form(self::getFormComponents()),
            ])
            ->actions([
                EditAction::make()->form(self::getFormComponents()),
                DeleteAction::make(),
            ]);
    }
}