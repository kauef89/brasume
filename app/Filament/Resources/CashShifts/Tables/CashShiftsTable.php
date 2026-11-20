<?php

namespace App\Filament\Resources\CashShifts\Tables;

use App\Enums\AuditDiagnosticStatus;
use App\Enums\ShiftStatus;
use App\Models\CashShift;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\RestaurantOps\OperationalShiftClosureService;
use App\Services\RestaurantOps\ShiftAuditService;
use App\Services\RestaurantOps\ShiftCashierService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CashShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Turno #')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('bankAccount.name')
                    ->label('Gaveta / Ponto')
                    ->weight(FontWeight::SemiBold)
                    ->searchable(),

                TextColumn::make('opener.name')
                    ->label('Operador')
                    ->description(fn (CashShift $record) => $record->closer?->name ? "Fechou: {$record->closer->name}" : null),

                TextColumn::make('opened_at')
                    ->label('Abertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                // DIAGNÓSTICO INTELIGENTE DE AUDITORIA (ENUM UNIFICADO)
                TextColumn::make('audit_diagnostic')
                    ->label('Auditoria / Integridade')
                    ->badge()
                    ->state(function (CashShift $record): AuditDiagnosticStatus {
                        $statusVal = $record->status instanceof \BackedEnum ? $record->status->value : (string) $record->status;

                        return AuditDiagnosticStatus::fromShiftSummary(
                            shiftStatus: $statusVal,
                            cashDiff: (float) $record->difference,
                            electronicDiff: (float) ($record->apos_breakage_amount ?? 0),
                            hasElectronicProof: true,
                            correctedCount: (int) ($record->corrected_errors_count ?? 0)
                        );
                    })
                    ->description(function (CashShift $record): ?string {
                        if (($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'open') {
                            return null;
                        }

                        $cashDiff = (float) $record->difference;
                        $aposDiff = (float) ($record->apos_breakage_amount ?? 0);

                        $parts = [];
                        if (abs($cashDiff) >= 0.05) {
                            $cashSign = $cashDiff > 0 ? '+' : '-';
                            $parts[] = 'Cofre: ' . $cashSign . 'R$ ' . number_format(abs($cashDiff), 2, ',', '.');
                        }
                        if (abs($aposDiff) >= 0.05) {
                            $aposSign = $aposDiff > 0 ? '+' : '-';
                            $parts[] = 'Dif. POS: ' . $aposSign . 'R$ ' . number_format(abs($aposDiff), 2, ',', '.');
                        }

                        return ! empty($parts) ? implode(' | ', $parts) : null;
                    }),

                TextColumn::make('difference')
                    ->label('Dif. Cofre')
                    ->money('BRL')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'info' : 'gray'))
                    ->visible(fn ($record) => $record && (($record->status instanceof \BackedEnum ? $record->status->value : $record->status) !== 'open'))
                    ->sortable(),

                TextColumn::make('apos_breakage_amount')
                    ->label('Dif. Adquirente')
                    ->money('BRL')
                    ->color(fn ($state) => abs((float) $state) >= 0.05 ? 'warning' : 'gray')
                    ->visible(fn ($record) => $record && (($record->status instanceof \BackedEnum ? $record->status->value : $record->status) !== 'open'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação do Turno')
                    ->options([
                        'open'        => 'Aberto',
                        'closed'      => 'Fechado (Pendente Auditoria)',
                        'reconciled'  => 'Auditado & Conciliado',
                        'homologated' => 'Homologado c/ Ressalva',
                    ]),

                Filter::make('problematic_shifts')
                    ->label('Pendentes com Problema / Quebra')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'closed')
                        ->where(function ($q) {
                            $q->where('has_breakage', true)
                                ->orWhereRaw('ABS(closing_balance_actual - closing_balance_expected) >= 0.05')
                                ->orWhereRaw('ABS(apos_breakage_amount) >= 0.05');
                        })),

                Filter::make('homologated_shifts')
                    ->label('Homologados com Ressalva')
                    ->query(fn (Builder $query): Builder => $query->where('status', 'homologated')),
            ])
            ->actions([
                // ==========================================
                // 1. SUPRIMENTO (Entrada de Dinheiro)
                // ==========================================
                Action::make('suprimento')
                    ->label('Suprimento')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (CashShift $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'open')
                    ->form([
                        TextInput::make('amount')->label('Valor (R$)')->numeric()->prefix('R$')->required(),
                        TextInput::make('description')->label('Motivo')->default('Troco extra / Suprimento')->required(),
                    ])
                    ->action(function (CashShift $record, array $data) {
                        app(ShiftCashierService::class)->recordTransaction([
                            'type'        => 'in',
                            'amount'      => $data['amount'],
                            'description' => $data['description'],
                        ], auth()->user()->name ?? 'Sistema', $record->bank_account_id);

                        Notification::make()->title('Suprimento Registrado!')->success()->send();
                    }),

                // ==========================================
                // 2. SANGRIA (Retirada de Dinheiro)
                // ==========================================
                Action::make('sangria')
                    ->label('Sangria')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('warning')
                    ->visible(fn (CashShift $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'open')
                    ->form([
                        TextInput::make('amount')->label('Valor (R$)')->numeric()->prefix('R$')->required(),
                        TextInput::make('description')->label('Motivo')->default('Retirada de excesso / Sangria')->required(),
                    ])
                    ->action(function (CashShift $record, array $data) {
                        app(ShiftCashierService::class)->recordTransaction([
                            'type'        => 'out',
                            'amount'      => $data['amount'],
                            'description' => $data['description'],
                        ], auth()->user()->name ?? 'Sistema', $record->bank_account_id);

                        Notification::make()->title('Sangria Registrada!')->success()->send();
                    }),

                // ==========================================
                // 3. FECHAR CAIXA
                // ==========================================
                Action::make('fechar_caixa')
                    ->label('Fechar Caixa')
                    ->icon('heroicon-m-lock-closed')
                    ->color('danger')
                    ->visible(fn (CashShift $record) => ($record->status instanceof \BackedEnum ? $record->status->value : $record->status) === 'open')
                    ->form(function (CashShift $record) {
                        $entradas = Transaction::where('bank_account_id', $record->bank_account_id)
                            ->where('transaction_date', '>=', $record->opened_at)
                            ->where('type', 'in')
                            ->sum('amount');

                        $saidas = Transaction::where('bank_account_id', $record->bank_account_id)
                            ->where('transaction_date', '>=', $record->opened_at)
                            ->where('type', 'out')
                            ->sum('amount');

                        $expected = $record->opening_balance + $entradas - $saidas;

                        return [
                            TextInput::make('closing_balance_expected')
                                ->label('Valor Esperado (Fundo + Vendas + Suprimentos - Sangrias)')
                                ->numeric()
                                ->prefix('R$')
                                ->default($expected)
                                ->readOnly()
                                ->extraInputAttributes(['style' => 'font-weight: bold; background-color: #f3f4f6;']),

                            TextInput::make('closing_balance_actual')
                                ->label('Valor Físico Contado na Gaveta')
                                ->numeric()
                                ->prefix('R$')
                                ->required()
                                ->autofocus(),

                            Textarea::make('notes')
                                ->label('Justificativa (Obrigatório se houver diferença)'),
                        ];
                    })
                    ->action(function (CashShift $record, array $data): void {
                        $opClosure = app(OperationalShiftClosureService::class);
                        $blockers = $opClosure->getPendingShiftBlockers($record->id);

                        if ($blockers['has_blockers']) {
                            Notification::make()
                                ->title('Bloqueio Operacional')
                                ->body('Impossível fechar o turno: existem pedidos ativos ou comandas pendentes.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $cashierService = app(ShiftCashierService::class);
                        $payload = [
                            'amount'          => $data['closing_balance_actual'],
                            'retained_amount' => 0,
                            'observation'     => $data['notes'] ?? null,
                            'details'         => null,
                        ];

                        $closed = $cashierService->closeShift($record, $payload, auth()->id(), auth()->user()?->name ?? 'Administrador');
                        $opClosure->closeShift($closed->id, true);

                        Notification::make()->title('Caixa Fechado com sucesso!')->success()->send();
                    }),

                // ==========================================
                // 4. AUDITORIA INTELIGENTE & CIÊNCIA/CORREÇÃO
                // ==========================================
                Action::make('auditoria')
                    ->label('Auditoria')
                    ->icon('heroicon-m-magnifying-glass-circle')
                    ->color(function (CashShift $record): string {
                        $statusVal = $record->status instanceof \BackedEnum ? $record->status->value : $record->status;
                        if (in_array($statusVal, ['reconciled', 'homologated'], true)) {
                            return 'gray';
                        }

                        return ($record->has_breakage || abs((float) $record->difference) >= 0.05 || abs((float) ($record->apos_breakage_amount ?? 0)) >= 0.05)
                            ? 'danger'
                            : 'gray';
                    })
                    ->visible(fn (CashShift $record) => in_array(($record->status instanceof \BackedEnum ? $record->status->value : $record->status), ['closed', 'reconciled', 'homologated'], true))
                    ->url(fn (CashShift $record) => \App\Filament\Resources\CashShifts\CashShiftResource::getUrl('audit', ['record' => $record])),

                ViewAction::make()->iconButton(),
            ]);
    }
}
