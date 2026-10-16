<?php

namespace App\Filament\Resources\BankAccounts\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountReceivablesRelationManager extends RelationManager
{
    protected static string $relationship = 'accountReceivables';
    protected static ?string $title = 'Recebimentos (Fluxo de Caixa)';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-up';

    public function table(Table $table): Table
        {
            return $table
                // 🚀 A MÁGICA 3.0: Derived Tables (Subconsultas)
                // Embrulhamos a tabela numa subconsulta pura. O MySQL agora reconhecerá os aliases no WHERE!
                ->modifyQueryUsing(function (Builder $query) {
                    $subquery = DB::table('account_receivables')
                        ->select('account_receivables.*')
                        ->selectRaw('DATE_FORMAT(due_date, "%Y-%m") as due_month')
                        ->selectRaw('DATE_FORMAT(due_date, "%x-W%v") as due_week');

                    return $query->fromSub($subquery, 'account_receivables');
                })

                ->recordTitleAttribute('description')
                ->defaultGroup('due_date')
                ->groupsOnly()

                // 🚀 OS TRÊS NÍVEIS DE AGRUPAMENTO (BI)
                ->groups([
                    Group::make('due_date')
                        ->label('Por Dia')
                        ->date('d/m/Y')
                        ->collapsible(),

                    Group::make('due_week')
                        ->label('Por Semana')
                        ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('due_date', $direction))
                        ->getTitleFromRecordUsing(fn ($record) => $record->due_date ? 'Semana ' . Carbon::parse($record->due_date)->format('W / Y') : 'Sem data')
                        ->collapsible(),

                    Group::make('due_month')
                        ->label('Por Mês')
                        ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('due_date', $direction))
                        ->getTitleFromRecordUsing(fn ($record) => $record->due_date ? Carbon::parse($record->due_date)->format('m / Y') : 'Sem data')
                        ->collapsible(),
                ])
                ->columns([

                    // ==========================================
                    // COLUNAS DE TEXTO COM ÂNCORAS INVISÍVEIS (ALINHAMENTO)
                    // ==========================================
                    Tables\Columns\TextColumn::make('due_date')
                        ->label('Previsão')
                        ->date('d/m/Y')
                        ->sortable()
                        ->summarize(
                            Summarizer::make()
                                ->label('Totais do Grupo')
                                ->using(fn () => new HtmlString('&nbsp;'))
                        ),

                    Tables\Columns\TextColumn::make('description')
                        ->label('Origem / Venda')
                        ->searchable()
                        ->summarize(
                            Summarizer::make()
                                ->label('')
                                ->using(fn () => new HtmlString('&nbsp;'))
                        ),

                    Tables\Columns\TextColumn::make('payment_method')
                        ->label('Forma Pgto.')
                        ->badge()
                        ->color('gray')
                        ->summarize(
                            Summarizer::make()
                                ->label('')
                                ->using(fn () => new HtmlString('&nbsp;'))
                        ),

                    // ==========================================
                    // COLUNAS FINANCEIRAS COM SOMA SILENCIOSA
                    // ==========================================
                    Tables\Columns\TextColumn::make('gross_amount')
                        ->label('Valor Bruto')
                        ->money('BRL')
                        ->color('gray')
                        ->size('sm')
                        ->summarize(
                            Summarizer::make()
                                ->using(fn ($query) => $query->sum('gross_amount'))
                                ->money('BRL')
                        ),

                    Tables\Columns\TextColumn::make('fee_amount')
                        ->label('Taxa (-)')
                        ->money('BRL')
                        ->color('danger')
                        ->size('sm')
                        ->summarize(
                            Summarizer::make()
                                ->using(fn ($query) => $query->sum('fee_amount'))
                                ->money('BRL')
                        ),

                    Tables\Columns\TextColumn::make('net_amount')
                        ->label('Líquido Esperado')
                        ->money('BRL')
                        ->weight('bold')
                        ->color('success')
                        ->summarize(
                            Summarizer::make()
                                ->using(fn ($query) => $query->sum('net_amount'))
                                ->money('BRL')
                        ),

                    // ==========================================
                    // STATUS E O TERMÔMETRO DE SAÚDE (BI)
                    // ==========================================
                    Tables\Columns\TextColumn::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'pending' => 'A Receber',
                            'paid' => 'Realizado',
                            'anticipated' => 'Antecipado',
                            'cancelled' => 'Cancelado',
                            default => $state,
                        })
                        ->colors([
                            'warning' => 'pending',
                            'success' => 'paid',
                            'info' => 'anticipated',
                            'danger' => 'cancelled',
                        ])
                        ->summarize(
                            Summarizer::make()
                                ->using(function ($query) {
                                    // 1. O total que deveria cair na conta neste período
                                    $totalAmount = (clone $query)->where('status', '!=', 'cancelled')->sum('net_amount');

                                    // 2. O total que já caiu na conta
                                    $paidAmount = (clone $query)->whereIn('status', ['paid', 'anticipated'])->sum('net_amount');

                                    // Se não houver valor, mantemos a âncora vazia para não quebrar o layout
                                    if ($totalAmount <= 0) return new HtmlString('&nbsp;');

                                    // 3. Calcula a percentagem
                                    $percent = round(($paidAmount / $totalAmount) * 100);

                                    // 4. Retorna a Badge Sintética baseada na saúde
                                    if ($percent == 100) {
                                        return new HtmlString('<span class="text-success-600 font-medium uppercase text-[10px] tracking-wider">100% Recebido</span>');
                                    } elseif ($percent == 0) {
                                        return new HtmlString('<span class="text-danger-600 font-medium uppercase text-[10px] tracking-wider">0% Recebido</span>');
                                    } else {
                                        return new HtmlString("<span class='text-warning-600 font-medium uppercase text-[10px] tracking-wider'>{$percent}% Recebido</span>");
                                    }
                                })
                        ),
                ])
                ->filters([
                    Tables\Filters\SelectFilter::make('status')
                        ->label('Situação')
                        ->options([
                            'pending' => 'A Receber (Esperado)',
                            'paid' => 'Realizado (Caiu na Conta)',
                        ]),

                    Filter::make('due_date_filter')
                        ->label('Filtrar Período')
                        ->form([
                            Grid::make(2)->schema([
                                DatePicker::make('created_from')->label('De (Previsão)'),
                                DatePicker::make('created_until')->label('Até (Previsão)'),
                            ])
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when(
                                    $data['created_from'],
                                    fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                                )
                                ->when(
                                    $data['created_until'],
                                    fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                                );
                        }),
                ])
                ->defaultSort('due_date', 'asc');
        }
    }
