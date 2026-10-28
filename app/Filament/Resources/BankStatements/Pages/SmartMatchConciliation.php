<?php

namespace App\Filament\Resources\BankStatements\Pages;

use BackedEnum;
use App\Filament\Resources\BankStatements\BankStatementResource;
use App\Models\BankStatement;
use App\Services\Accounting\BankMatchScoringService;
use App\Filament\Resources\BankStatements\Schemas\ConciliationTabsSchema;
use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class SmartMatchConciliation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = BankStatementResource::class;

    // Propriedade não-estática exigida pelo Filament
    protected string $view = 'filament.resources.bank-statements.pages.smart-match-conciliation';

    protected static ?string $title = 'Conciliação Inteligente em Lote';

    // Assinatura estática compatível com PHP 8.5 / Filament
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalcular')
                ->label('Varrer e Recalcular Scores')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(function () {
                    $service = new BankMatchScoringService();
                    $pending = BankStatement::where('status', 'pending')->where('amount', '<', 0)->get();

                    foreach ($pending as $stmt) {
                        $matches = $service->findMatchesFor($stmt);
                        if ($matches->isNotEmpty()) {
                            $top = $matches->first();
                            $stmt->update([
                                'match_score' => $top['score'],
                                'suggested_payable_id' => $top['candidate']->id,
                            ]);
                        } else {
                            $stmt->update([
                                'match_score' => 0,
                                'suggested_payable_id' => null,
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Inteligência recalculada com sucesso!')
                        ->success()
                        ->send();
                }),

            Action::make('voltar')
                ->label('Voltar aos Extratos')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(fn () => BankStatementResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BankStatement::query()
                    ->where('status', 'pending')
                    ->where('amount', '<', 0)
                    ->where('match_score', '>=', 40)
                    ->whereNotNull('suggested_payable_id')
                    ->with(['suggestedPayable.supplier', 'suggestedPayable.employee', 'bankAccount'])
            )
            ->defaultSort('match_score', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('match_score')
                    ->label('Confiança')
                    ->badge()
                    ->sortable()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 95 => 'success',
                        $state >= 80 => 'info',
                        $state >= 60 => 'warning',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => "{$state}% Match"),

                Tables\Columns\TextColumn::make('bank_date')
                    ->label('Data (Banco)')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('counterparty_name')
                    ->label('Contraparte (Banco)')
                    ->searchable()
                    ->weight('bold')
                    ->limit(16),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor (Banco)')
                    ->state(fn (BankStatement $record) => abs((float) $record->amount))
                    ->money('BRL')
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('suggestedPayable.supplier.name')
                    ->label('Fornecedor (Sistema)')
                    ->state(fn (BankStatement $record) => $record->suggestedPayable?->supplier?->name
                        ?? ($record->suggestedPayable?->supplier?->document_name
                        ?? ($record->suggestedPayable?->employee?->name ?? 'Fornecedor S/N')))
                    ->searchable(),

                Tables\Columns\TextColumn::make('suggestedPayable.due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('suggestedPayable.amount')
                    ->label('Valor (Sistema)')
                    ->money('BRL')
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('diferenca')
                    ->label('Divergência')
                    ->badge()
                    ->state(function (BankStatement $record) {
                        if (!$record->suggestedPayable) return '-';
                        $bAmt = abs((float) $record->amount);
                        $sAmt = abs((float) $record->suggestedPayable->amount);
                        $diff = $bAmt - $sAmt;

                        if (abs($diff) < 0.02) return 'Exato';
                        $prefix = $diff > 0 ? '+R$ ' : '-R$ ';
                        return $prefix . number_format(abs($diff), 2, ',', '.');
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Exato' => 'success',
                        '-'     => 'gray',
                        default => 'warning',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('score_range')
                    ->label('Faixa de Confiança')
                    ->options([
                        'high'   => 'Alta Confiança (≥ 90%)',
                        'medium' => 'Boa Confiança (≥ 80%)',
                        'all'    => 'Todas as Sugestões (≥ 40%)',
                    ])
                    ->default('all')
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'high'   => $query->where('match_score', '>=', 90),
                            'medium' => $query->where('match_score', '>=', 80),
                            default  => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-m-check')
                    ->button()
                    ->color('success')
                    ->action(function (BankStatement $record) {
                        if (!$record->suggested_payable_id) return;
                        ConciliationTabsSchema::processConciliation(
                            ['existing_payable_id' => 'payable_' . $record->suggested_payable_id],
                            $record
                        );
                        Notification::make()->title('Transação conciliada!')->success()->send();
                    }),

                Action::make('descartar')
                    ->label('Descartar')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->action(function (BankStatement $record) {
                        $record->update([
                            'match_score' => 0,
                            'suggested_payable_id' => null,
                        ]);
                        Notification::make()->title('Sugestão descartada da fila.')->info()->send();
                    }),

                Action::make('manual')
                    ->label('Outro...')
                    ->icon('heroicon-m-pencil')
                    ->color('gray')
                    ->slideOver()
                    ->form(fn (BankStatement $record) => [
                        ConciliationTabsSchema::make($record),
                    ])
                    ->action(function (array $data, BankStatement $record) {
                        ConciliationTabsSchema::processConciliation($data, $record);
                        Notification::make()->title('Transação conciliada com sucesso!')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('aprovar_selecionados')
                    ->label('Aprovar e Conciliar Selecionados')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprovar Conciliações em Lote')
                    ->modalDescription('Tem certeza que deseja conciliar todas as transações selecionadas com seus respectivos fornecedores sugeridos?')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status !== 'pending' || !$record->suggested_payable_id) continue;
                            ConciliationTabsSchema::processConciliation(
                                ['existing_payable_id' => 'payable_' . $record->suggested_payable_id],
                                $record
                            );
                            $count++;
                        }

                        Notification::make()
                            ->title("{$count} transações conciliadas com sucesso!")
                            ->success()
                            ->send();
                    }),

                BulkAction::make('descartar_selecionados')
                    ->label('Descartar Sugestões Selecionadas')
                    ->icon('heroicon-m-x-mark')
                    ->color('gray')
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            $record->update([
                                'match_score' => 0,
                                'suggested_payable_id' => null,
                            ]);
                        }
                        Notification::make()->title('Sugestões descartadas.')->info()->send();
                    }),
            ])
            ->emptyStateHeading('🎉 Nenhuma sugestão pendente!')
            ->emptyStateDescription('Todos os lançamentos automáticos já foram conciliados ou descartados.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
