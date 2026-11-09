<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Models\AccountPayable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeePayablesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountPayables';
    protected static ?string $title = 'Serviços Extras & Contas a Pagar';
    protected static ?string $modelLabel = 'Lançamento a Pagar';
    protected static ?string $pluralModelLabel = 'Contas a Pagar';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getFormComponents(): array
    {
        return [
            TextInput::make('description')
                ->label('Descrição do Serviço / Diária')
                ->placeholder('Ex: Diária extra de apoio, bônus por meta')
                ->required()
                ->columnSpan(2),

            TextInput::make('amount')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->required(),

            DatePicker::make('due_date')
                ->label('Data de Vencimento')
                ->default(now())
                ->required(),

            Select::make('status')
                ->label('Status')
                ->options([
                    'pending' => 'Pendente',
                    'paid'    => 'Pago',
                    'cancelled' => 'Cancelado',
                ])
                ->default('pending')
                ->required(),

            DatePicker::make('payment_date')
                ->label('Data do Pagamento')
                ->visible(fn ($get) => $get('status') === 'paid'),

            Textarea::make('observations')
                ->label('Observações')
                ->placeholder('Detalhes da contratação do serviço extra')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::getFormComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('due_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->weight('bold')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid'      => 'success',
                        'pending'   => 'warning',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'paid'      => 'Pago',
                        'pending'   => 'Pendente',
                        'cancelled' => 'Cancelado',
                        default     => ucfirst($state ?? '-'),
                    }),

                Tables\Columns\TextColumn::make('payment_date')
                    ->label('Pago em')
                    ->date('d/m/Y')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'pending' => 'Pendente',
                        'paid'    => 'Pago',
                    ]),
                Tables\Filters\Filter::make('due_date')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Lançar Pagamento Extra')
                    ->form(self::getFormComponents()),
            ])
            ->actions([
                EditAction::make()->form(self::getFormComponents()),
                DeleteAction::make(),
            ]);
    }
}