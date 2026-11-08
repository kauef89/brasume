<?php

namespace App\Filament\Resources\Payrolls\Tables;

use App\Filament\Resources\Payrolls\PayrollResource;
use App\Models\BankAccount;
use App\Models\Payroll;
use App\Models\Payslip;
use App\Services\Accounting\AccountingExportService;
use App\Services\Financial\SicrediCnab240Service;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reference_period', 'desc')
            ->recordUrl(fn (Payroll $record): string => PayrollResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('reference_period')
                    ->label('Competência')
                    ->date('m/Y')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payslips_count')
                    ->label('Colaboradores')
                    ->counts('payslips')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_earnings')
                    ->label('Total Bruto')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_deductions')
                    ->label('Deduções (INSS/IRRF)')
                    ->money('BRL')
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_net')
                    ->label('Total Líquido')
                    ->money('BRL')
                    ->weight('black')
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_fgts')
                    ->label('FGTS Total')
                    ->money('BRL')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
                        ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status da Folha')
                    ->options(\App\Enums\PayrollStatus::class),
                Tables\Filters\Filter::make('reference_period')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('period_from')->label('De'),
                        \Filament\Forms\Components\DatePicker::make('period_until')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['period_from'], fn ($q, $date) => $q->whereDate('reference_period', '>=', $date))
                            ->when($data['period_until'], fn ($q, $date) => $q->whereDate('reference_period', '<=', $date));
                    }),
            ])
            ->actions([
                // 1. Exportação CNAB 240 Sicredi
                Action::make('exportCnab')
                    ->label('CNAB 240')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->disabled(fn (Payroll $record): bool => (float) $record->total_net <= 0)
                    ->tooltip(fn (Payroll $record): ?string => (float) $record->total_net <= 0 ? 'Folha sem valores líquidos para gerar remessa.' : null)
                    ->modalHeading(fn (Payroll $record) => 'Exportar CNAB 240 (Sicredi) — Competência ' . Carbon::parse($record->reference_period)->format('m/Y'))
                    ->modalDescription('Gere o arquivo .REM de folha de pagamento para as contas-salário da cooperativa Sicredi.')
                    ->modalSubmitActionLabel('Gerar e Baixar .REM')
                    ->form(fn (Payroll $record): array => [
                        DatePicker::make('payment_date')
                            ->label('Data de Pagamento (Débito)')
                            ->default(now())
                            ->required(),

                        CheckboxList::make('payslip_ids')
                            ->label('Colaboradores a Incluir no Lote')
                            ->helperText('Colaboradores marcados possuem Cooperativa e Conta Sicredi cadastradas.')
                            ->options(function () use ($record) {
                                $payslips = Payslip::with('employee')->where('payroll_id', $record->id)->get();
                                $opts = [];
                                foreach ($payslips as $p) {
                                    $emp = $p->employee;
                                    $hasAccount = !empty($emp?->bank_agency) && !empty($emp?->bank_account);
                                    $accountInfo = $hasAccount
                                        ? "Ag: {$emp->bank_agency} / Cc: {$emp->bank_account}-{$emp->bank_account_digit}"
                                        : "⚠️ SEM CONTA SICREDI";
                                    $opts[$p->id] = "{$emp->name} — Líquido: R$ " . number_format((float) $p->net_salary, 2, ',', '.') . " [{$accountInfo}]";
                                }
                                return $opts;
                            })
                            ->default(function () use ($record) {
                                return Payslip::where('payroll_id', $record->id)
                                    ->whereHas('employee', function ($q) {
                                        $q->whereNotNull('bank_agency')->where('bank_agency', '!=', '')
                                          ->whereNotNull('bank_account')->where('bank_account', '!=', '');
                                    })
                                    ->pluck('id')
                                    ->toArray();
                            })
                            ->required()
                            ->columns(1),
                    ])
                    ->action(function (Payroll $record, array $data): ?StreamedResponse {
                        $sicredi = BankAccount::where('bank_code', '748')->first();

                        if (! $sicredi || empty($sicredi->account_number)) {
                            Notification::make()
                                ->title('Conta Sicredi PJ não configurada')
                                ->body('Configure a agência e conta Sicredi em Bancos antes de gerar a remessa.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        $payslips = Payslip::whereIn('id', $data['payslip_ids'])->get();

                        if ($payslips->isEmpty()) {
                            Notification::make()
                                ->title('Nenhum colaborador selecionado')
                                ->body('Selecione ao menos um holerite para gerar a remessa.')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $invalid = $payslips->filter(function ($p) {
                            $emp = $p->employee;
                            return empty($emp?->bank_agency) || empty($emp?->bank_account);
                        });

                        if ($invalid->isNotEmpty()) {
                            $names = $invalid->map(fn ($p) => $p->employee?->name ?? "#{$p->id}")->implode(', ');
                            Notification::make()
                                ->title('Dados bancários pendentes')
                                ->body("Os seguintes colaboradores selecionados não têm conta Sicredi: {$names}")
                                ->danger()
                                ->send();

                            return null;
                        }

                        $paymentDate = Carbon::parse($data['payment_date']);
                        $refMonth = Carbon::parse($record->reference_period)->format('m/Y');
                        $service = new SicrediCnab240Service($sicredi, $paymentDate);
                        $result = $service->generateSalaryBatch($payslips, "FOLHA {$refMonth}");

                        Notification::make()
                            ->title('Lote Sicredi Gerado!')
                            ->body("Lote #{$result['batch_id']} criado com sucesso (R$ " . number_format($result['total_amount'], 2, ',', '.') . " em {$result['count']} holerites).")
                            ->success()
                            ->send();

                        return response()->streamDownload(function () use ($result) {
                            echo $result['content'];
                        }, $result['filename'], [
                            'Content-Type' => 'text/plain; charset=UTF-8',
                        ]);
                    }),

                // 2. Disparo de Relatório ao DP (Inativo quando valores estiverem vazios)
                Action::make('sendAccountingReport')
                    ->label('Enviar ao DP')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->disabled(fn (Payroll $record): bool => (float) $record->total_net <= 0 && (float) $record->total_earnings <= 0)
                    ->tooltip(function (Payroll $record): ?string {
                        if ((float) $record->total_net <= 0 && (float) $record->total_earnings <= 0) {
                            return 'Dados de folha vazios. Importe o PDF ou calcule os valores antes de enviar ao DP.';
                        }
                        return null;
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Enviar Relatório ao DP')
                    ->modalDescription(fn (Payroll $record) => 'Deseja despachar por e-mail os relatórios de fechamento da competência ' . Carbon::parse($record->reference_period)->format('m/Y') . ' para a contabilidade?')
                    ->modalSubmitActionLabel('Confirmar e Enviar')
                    ->action(function (Payroll $record) {
                        try {
                            $date = Carbon::parse($record->reference_period);
                            $service = new AccountingExportService();
                            $service->sendMonthlyClosing($date);

                            Notification::make()
                                ->title('Relatórios enviados!')
                                ->body('Os e-mails da competência ' . $date->format('m/Y') . ' foram despachados para a contabilidade.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao enviar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // 3. Exclusão Segura (Bloqueada para folhas pagas ou com lote bancário ativo)
                DeleteAction::make()
                    ->disabled(fn (Payroll $record): bool => $record->status === 'paid' || !empty($record->payment_batch_id))
                    ->tooltip(function (Payroll $record): ?string {
                        if ($record->status === 'paid') {
                            return 'Não é permitido excluir uma folha com status Paga.';
                        }
                        if (!empty($record->payment_batch_id)) {
                            return 'Não é permitido excluir: há um Lote Bancário vinculado a esta folha.';
                        }
                        return null;
                    })
                    ->modalHeading('Excluir Folha de Pagamento')
                    ->modalDescription('Esta ação removerá permanentemente a folha e todos os recibos individuais (holerites) atrelados a ela. Deseja continuar?')
                    ->action(function (Payroll $record) {
                        DB::transaction(function () use ($record) {
                            // Limpa os holerites e rubricas dependentes antes de deletar a folha
                            foreach ($record->payslips as $payslip) {
                                if (method_exists($payslip, 'items')) {
                                    $payslip->items()->delete();
                                }
                                $payslip->delete();
                            }
                            $record->delete();
                        });

                        Notification::make()
                            ->title('Folha excluída com sucesso')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
