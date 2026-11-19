<?php

namespace App\Filament\Resources\LoanContracts;

use App\Filament\Resources\LoanContracts\Pages;
use App\Filament\Resources\LoanContracts\RelationManagers\InstallmentsRelationManager;
use App\Models\BankAccount;
use App\Models\LoanContract;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LoanContractResource extends Resource
{
    protected static ?string $model = LoanContract::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';
    protected static ?string $slug = 'emprestimos';
    protected static ?string $modelLabel = 'Contrato de Empréstimo';
    protected static ?string $pluralModelLabel = 'Empréstimos & Giro';
    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identificação do Contrato Bancário')
                ->description('Informe o número do contrato exatamente como aparece no extrato OFX (ex: C60930806) para conciliação automática das parcelas.')
                ->schema([
                    Grid::make(3)->schema([
                        Select::make('bank_account_id')
                            ->label('Conta Bancária Credora')
                            ->options(fn () => BankAccount::bankInstitutions()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload(),

                        TextInput::make('contract_number')
                            ->label('Número do Contrato (OFX)')
                            ->placeholder('Ex: C60930806')
                            ->required()
                            ->maxLength(60),

                        TextInput::make('lender_name')
                            ->label('Instituição Financeira')
                            ->default('Banco Cooperativo Sicredi S.A.')
                            ->required()
                            ->maxLength(120),
                    ]),

                    Grid::make(3)->schema([
                        TextInput::make('description')
                            ->label('Finalidade / Descrição')
                            ->placeholder('Ex: Capital de Giro Sicredi')
                            ->required()
                            ->columnSpan(2),

                        Select::make('status')
                            ->label('Situação')
                            ->options([
                                'active'    => 'Ativo (Em Amortização)',
                                'paid_off'  => 'Quitado Integralmente',
                                'cancelled' => 'Cancelado',
                            ])
                            ->default('active')
                            ->required(),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make('Condições Financeiras, Principal & Juros')
                ->description('Ao salvar, o sistema gera automaticamente todas as parcelas no Contas a Pagar como Débito Automático e separa Amortização de Principal vs. Juros na DRE.')
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('principal_amount')
                            ->label('Valor de Face / Principal (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Valor contratado antes dos juros.'),

                        TextInput::make('origination_fee_amount')
                            ->label('Tarifa / IOF na Liberação (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->default(0)
                            ->live(onBlur: true)
                            ->helperText('Ex: R$ 500,00 descontado na abertura.'),

                        TextInput::make('installments_count')
                            ->label('Qtd. de Parcelas')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->required()
                            ->live(onBlur: true),

                        TextInput::make('installment_amount')
                            ->label('Valor de Cada Parcela (R$)')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->live(onBlur: true)
                            ->helperText('Ex: R$ 998,77'),
                    ]),

                    Grid::make(3)->schema([
                        DatePicker::make('disbursement_date')
                            ->label('Data da Liberação do Crédito')
                            ->required(),

                        DatePicker::make('first_due_date')
                            ->label('Vencimento da 1ª Parcela')
                            ->required(),

                        Placeholder::make('financial_summary_preview')
                            ->label('Resumo Calculado')
                            ->content(function (Get $get) {
                                $principal = (float) ($get('principal_amount') ?? 0);
                                $fee = (float) ($get('origination_fee_amount') ?? 0);
                                $count = max(1, (int) ($get('installments_count') ?? 1));
                                $inst = (float) ($get('installment_amount') ?? 0);

                                $totalPayable = round($count * $inst, 2);
                                $totalInterest = max(0.0, round($totalPayable - $principal, 2));
                                $netDisbursed = max(0.0, round($principal - $fee, 2));

                                $pFmt = number_format($netDisbursed, 2, ',', '.');
                                $tFmt = number_format($totalPayable, 2, ',', '.');
                                $jFmt = number_format($totalInterest, 2, ',', '.');

                                return new HtmlString("
                                    <div class='text-xs space-y-0.5 p-2.5 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700'>
                                        <div>Líquido Liberado: <b>R$ {$pFmt}</b></div>
                                        <div>Total a Pagar ({$count}x): <b>R$ {$tFmt}</b></div>
                                        <div class='text-amber-600 dark:text-amber-400'>Total de Juros: <b>R$ {$jFmt}</b></div>
                                    </div>
                                ");
                            }),
                    ]),

                    Textarea::make('notes')
                        ->label('Observações do Contrato')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract_number')
                    ->label('Contrato')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descrição / Instituição')
                    ->description(fn (LoanContract $record) => $record->lender_name)
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('BRL')
                    ->sortable(),

                TextColumn::make('installments_progress')
                    ->label('Parcelas')
                    ->state(function (LoanContract $record) {
                        $paid = $record->installments()->where('status', 'paid')->count();
                        return "{$paid}/{$record->installments_count}x de R$ " . number_format((float) $record->installment_amount, 2, ',', '.');
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('total_interest_amount')
                    ->label('Juros Totais')
                    ->money('BRL')
                    ->color('warning'),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid_off'  => 'success',
                        'active'    => 'warning',
                        'cancelled' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paid_off'  => 'Quitado',
                        'active'    => 'Ativo',
                        'cancelled' => 'Cancelado',
                        default     => $state,
                    }),
            ])
            ->defaultSort('disbursement_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'active'    => 'Ativos',
                        'paid_off'  => 'Quitados',
                        'cancelled' => 'Cancelados',
                    ]),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoanContracts::route('/'),
            'create' => Pages\CreateLoanContract::route('/create'),
            'edit'   => Pages\EditLoanContract::route('/{record}/edit'),
        ];
    }
}