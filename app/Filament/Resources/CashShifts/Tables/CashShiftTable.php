<?php

namespace App\Filament\Resources\CashShifts\Tables;

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
use Filament\Forms\Components\Section;
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

class CashShiftTable
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

                // DIAGNÓSTICO INTELIGENTE DE AUDITORIA
                TextColumn::make('audit_diagnostic')
                    ->label('Auditoria / Integridade')
                    ->badge()
                    ->state(function (CashShift $record): string {
                        $isClosed = $record->status === ShiftStatus::CLOSED || $record->status?->value === 'closed' || $record->status === 'closed';
                        if (! $isClosed) {
                            return 'Em Operação';
                        }

                        $cashDiff = (float) $record->difference;
                        $aposDiff = (float) ($record->apos_breakage_amount ?? 0);
                        $hasCash = abs($cashDiff) >= 0.05;
                        $hasApos = abs($aposDiff) >= 0.05;

                        if (! $hasCash && ! $hasApos) {
                            return $record->corrected_errors_count > 0 ? 'Conciliado (Com Correção)' : '100% Conciliado';
                        }

                        // Se falta num lado e sobra no outro no mesmo valor = Troca de lançamento
                        if ($hasCash && $hasApos && abs($cashDiff + $aposDiff) <= 0.05) {
                            return 'Troca de Meio Detectada';
                        }

                        if ($hasCash && ! $hasApos) {
                            return $cashDiff < 0 ? 'Quebra de Cofre (Falta)' : 'Sobra no Cofre';
                        }

                        if ($hasApos && ! $hasCash) {
                            return 'Divergência Adquirente';
                        }

                        return 'Divergência Múltipla';
                    })
                    ->color(function (string $state): string {
                        return match ($state) {
                            '100% Conciliado'              => 'success',
                            'Conciliado (Com Correção)'    => 'info',
                            'Troca de Meio Detectada'      => 'warning',
                            'Divergência Adquirente'       => 'warning',
                            'Quebra de Cofre (Falta)'      => 'danger',
                            'Sobra no Cofre'               => 'info',
                            'Divergência Múltipla'         => 'danger',
                            default                        => 'gray',
                        };
                    })
                    ->description(function (CashShift $record): ?string {
                        if ($record->status === ShiftStatus::OPEN || $record->status?->value === 'open' || $record->status === 'open') {
                            return null;
                        }

                        $cashDiff = (float) $record->difference;
                        $aposDiff = (float) ($record->apos_breakage_amount ?? 0);

                        $parts = [];
                        if (abs($cashDiff) >= 0.05) {
                            $parts[] = 'Cofre: R$ ' . number_format($cashDiff, 2, ',', '.');
                        }
                        if (abs($aposDiff) >= 0.05) {
                            $parts[] = 'POS: R$ ' . number_format($aposDiff, 2, ',', '.');
                        }

                        return ! empty($parts) ? implode(' | ', $parts) : null;
                    }),

                TextColumn::make('difference')
                    ->label('Dif. Cofre')
                    ->money('BRL')
                    ->color(fn ($state) => $state < 0 ? 'danger' : ($state > 0 ? 'info' : 'gray'))
                    ->visible(fn ($record) => $record && ($record->status === ShiftStatus::CLOSED || $record->status?->value === 'closed' || $record->status === 'closed'))
                    ->sortable(),

                TextColumn::make('apos_breakage_amount')
                    ->label('Dif. Adquirente')
                    ->money('BRL')
                    ->color(fn ($state) => abs((float) $state) >= 0.05 ? 'warning' : 'gray')
                    ->visible(fn ($record) => $record && ($record->status === ShiftStatus::CLOSED || $record->status?->value === 'closed' || $record->status === 'closed'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação do Turno')
                    ->options([
                        'open'   => 'Aberto',
                        'closed' => 'Fechado',
                    ]),

                Filter::make('problematic_shifts')
                    ->label('Apenas Turnos com Problema / Quebra')
                    ->query(fn (Builder $query): Builder => $query->where(function ($q) {
                        $q->where('has_breakage', true)
                            ->orWhereRaw('ABS(closing_balance_actual - closing_balance_expected) >= 0.05')
                            ->orWhereRaw('ABS(apos_breakage_amount) >= 0.05');
                    })),
            ])
            ->actions([
                // ==========================================
                // 1. SUPRIMENTO (Entrada de Dinheiro)
                // ==========================================
                Action::make('suprimento')
                    ->label('Suprimento')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (CashShift $record) => $record->status === ShiftStatus::OPEN || $record->status?->value === 'open' || $record->status === 'open')
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
                    ->visible(fn (CashShift $record) => $record->status === ShiftStatus::OPEN || $record->status?->value === 'open' || $record->status === 'open')
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
                    ->visible(fn (CashShift $record) => $record->status === ShiftStatus::OPEN || $record->status?->value === 'open' || $record->status === 'open')
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
                    ->color(fn (CashShift $record) => $record->has_breakage || abs((float) $record->difference) >= 0.05 ? 'danger' : 'gray')
                    ->visible(fn (CashShift $record) => $record->status === ShiftStatus::CLOSED || $record->status?->value === 'closed' || $record->status === 'closed')
                    ->modalHeading(fn (CashShift $record) => "Auditoria de Fechamento - Turno #{$record->id}")
                    ->modalDescription('Diagnóstico cruzado entre dinheiro em cofre, recebimentos em maquininhas (POS) e pedidos.')
                    ->modalSubmitActionLabel('Salvar Parecer / Aplicar')
                    ->form(function (CashShift $record) {
                        /** @var ShiftAuditService $auditService */
                        $auditService = app(ShiftAuditService::class);
                        $diag = $auditService->diagnose(
                            $record,
                            (float) $record->closing_balance_actual,
                            $record->terminal_settlements ?? []
                        );

                        $cashDiff = (float) $diag['cash_difference'];
                        $elecDiff = (float) $diag['electronic_difference'];
                        $suggestion = $diag['suggestion'];

                        $diffHtml = "<div class='grid grid-cols-2 gap-4 p-4 rounded-xl border " . ($diag['has_discrepancy'] ? 'bg-danger-50 border-danger-200 dark:bg-danger-950/30 dark:border-danger-800' : 'bg-success-50 border-success-200 dark:bg-success-950/30 dark:border-success-800') . "'>";
                        $diffHtml .= "<div><span class='text-xs uppercase font-bold text-gray-500'>Cofre Físico (Dinheiro):</span><br><strong class='text-base " . ($cashDiff < 0 ? 'text-danger-600' : ($cashDiff > 0 ? 'text-info-600' : 'text-success-600')) . "'>R$ " . number_format($cashDiff, 2, ',', '.') . "</strong></div>";
                        $diffHtml .= "<div><span class='text-xs uppercase font-bold text-gray-500'>Maquininhas (POS / Fitas):</span><br><strong class='text-base " . ($elecDiff < 0 ? 'text-danger-600' : ($elecDiff > 0 ? 'text-info-600' : 'text-success-600')) . "'>R$ " . number_format($elecDiff, 2, ',', '.') . "</strong></div>";
                        $diffHtml .= "</div>";

                        $fields = [
                            Placeholder::make('resumo_financeiro')
                                ->label('Resultado da Conferência')
                                ->content(new HtmlString($diffHtml)),
                        ];

                        if ($suggestion) {
                            $sugHtml = "<div class='p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-700 rounded-lg text-amber-900 dark:text-amber-200 text-xs leading-relaxed'>";
                            $sugHtml .= "<strong>💡 Inteligência Detectou Troca de Modalidade:</strong><br>";
                            $sugHtml .= e($suggestion['reason']) . "<br>";
                            $sugHtml .= "<span class='font-semibold underline'>Ação Sugerida:</span> Alterar o Pedido #{$suggestion['order_id']} de <strong>{$suggestion['current_method_name']}</strong> para <strong>{$suggestion['suggested_method_name']}</strong>.";
                            $sugHtml .= "</div>";

                            $fields[] = Section::make('Sugestão de Correção Automática')
                                ->schema([
                                    Placeholder::make('sugestao_troca')
                                        ->label('')
                                        ->content(new HtmlString($sugHtml)),

                                    Radio::make('apply_suggested_fix')
                                        ->label('Deseja que o sistema corrija este lançamento agora?')
                                        ->options([
                                            'yes' => 'Sim, aplicar correção de modalidade no pedido',
                                            'no'  => 'Não, manter como está e apenas justificar',
                                        ])
                                        ->default('yes')
                                        ->required(),
                                ]);
                        }

                        $fields[] = Section::make('Parecer do Gestor / Ciência de Fechamento')
                            ->schema([
                                Textarea::make('manager_notes')
                                    ->label('Justificativa / Registro de Ciência do Turno')
                                    ->placeholder('Ex: Conferido com o operador. Quebra real de cofre devido a erro de troco / comprovante anexado.')
                                    ->rows(3)
                                    ->default($record->notes),
                            ]);

                        return $fields;
                    })
                    ->action(function (CashShift $record, array $data) {
                        /** @var ShiftAuditService $auditService */
                        $auditService = app(ShiftAuditService::class);
                        $operatorName = auth()->user()?->name ?? 'Gestor Administrativo';

                        // 1. Aplica a correção de meio trocado se confirmado pelo usuário
                        if (($data['apply_suggested_fix'] ?? 'no') === 'yes') {
                            $diag = $auditService->diagnose(
                                $record,
                                (float) $record->closing_balance_actual,
                                $record->terminal_settlements ?? []
                            );

                            if (! empty($diag['suggestion']['payment_id']) && ! empty($diag['suggestion']['suggested_method_id'])) {
                                $auditService->applyCorrection(
                                    $record,
                                    (int) $diag['suggestion']['payment_id'],
                                    (int) $diag['suggestion']['suggested_method_id'],
                                    $operatorName
                                );

                                Notification::make()
                                    ->title('Correção Aplicada com Sucesso!')
                                    ->body("O meio de pagamento do pedido #{$diag['suggestion']['order_id']} foi retificado e a quebra foi eliminada.")
                                    ->success()
                                    ->send();
                            }
                        }

                        // 2. Salva o parecer do gestor e atualiza as notas
                        if (! empty($data['manager_notes']) && $data['manager_notes'] !== $record->notes) {
                            $timestamp = now()->format('d/m/Y H:i');
                            $auditNote = "Ciência {$timestamp} por {$operatorName}: " . trim($data['manager_notes']);

                            $record->notes = $record->notes ? $record->notes . " | " . $auditNote : $auditNote;
                            $record->save();

                            Notification::make()
                                ->title('Parecer Registrado!')
                                ->body('Ciência e justificativa do turno gravadas no histórico.')
                                ->info()
                                ->send();
                        }
                    }),

                ViewAction::make()->iconButton(),
            ]);
    }
}