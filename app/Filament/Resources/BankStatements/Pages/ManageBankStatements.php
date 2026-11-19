<?php

namespace App\Filament\Resources\BankStatements\Pages;

use App\Filament\Resources\BankStatements\BankStatementResource;
use App\Filament\Traits\TeleportsTabs;
use Filament\Resources\Pages\ManageRecords;
use App\Filament\Resources\BankStatements\Actions\AutoConciliateAction;
use App\Filament\Resources\BankStatements\Actions\AutoConciliateBatchAction;
use App\Filament\Resources\BankStatements\Actions\ImportOfxAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BankStatement;

class ManageBankStatements extends ManageRecords
{
    use TeleportsTabs;
    protected static string $resource = BankStatementResource::class;

    public array $skippedIds = [];
    public ?int $currentStmtId = null;

    protected function getHeaderActions(): array
    {
        return [
            ImportOfxAction::make(),

            \Filament\Actions\ActionGroup::make([
                \Filament\Actions\Action::make('smart_match_queue')
                    ->label('Conciliação Inteligente')
                    ->icon('heroicon-m-bolt')
                    ->color('warning')
                    ->badge(fn () => \App\Models\BankStatement::where('status', 'pending')->where('match_score', '>=', 40)->whereNotNull('suggested_payable_id')->count() ?: null)
                    ->url(fn () => \App\Filament\Resources\BankStatements\BankStatementResource::getUrl('smart-matches')),

                // A nossa Ação passo a passo anterior (renomeie no ecrã se quiser)
                AutoConciliateAction::make()->label('Conciliação Passo a Passo')->color('gray')->icon('heroicon-m-forward'),

                \Filament\Actions\Action::make('reprocess_pending_statements')
                    ->label('Reprocessar Pendências')
                    ->icon('heroicon-m-arrow-path')
                    ->color('gray')
                    ->visible(fn () => \App\Models\BankStatement::where('status', 'pending')->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Reprocessar Lançamentos Pendentes')
                    ->modalDescription('Reavalia os lançamentos pendentes do extrato usando as regras atuais (Recorrentes/Débito Automático, Uso Pessoal, PIX de clientes e Repasses de Cartão) sem criar duplicatas.')
                    ->action(function (\App\Services\Accounting\OfxImportService $service) {
                        $res = $service->reprocessPending();
                        $total = $res['auto_matches_count']
                            + $res['auto_recurring_count']
                            + $res['auto_personal_count']
                            + $res['auto_fees_count']
                            + $res['auto_pix_count']
                            + $res['auto_card_batches'];
    
                        if ($total === 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Nenhuma nova correspondência automática')
                                ->body('Os itens restantes aguardam conferência manual ou regra/contrato específico.')
                                ->info()
                                ->send();
                            return;
                        }
    
                        $bits = [];
                        if ($res['auto_recurring_count'] > 0) $bits[] = "{$res['auto_recurring_count']} recorrente(s)/débito aut.";
                        if ($res['auto_matches_count'] > 0)   $bits[] = "{$res['auto_matches_count']} título(s) exato(s)";
                        if ($res['auto_personal_count'] > 0)  $bits[] = "{$res['auto_personal_count']} uso pessoal";
                        if ($res['auto_card_batches'] > 0)    $bits[] = "{$res['auto_card_batches']} lote(s) de cartão";
                        if ($res['auto_pix_count'] > 0)       $bits[] = "{$res['auto_pix_count']} PIX de cliente(s)";
                        if ($res['auto_fees_count'] > 0)      $bits[] = "{$res['auto_fees_count']} tarifa(s)";
    
                        \Filament\Notifications\Notification::make()
                            ->title('Reprocessamento Concluído!')
                            ->body('Conciliados: ' . implode(', ', $bits) . '.')
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Ferramentas')
                ->icon('heroicon-m-wrench-screwdriver')
                ->button()
                ->color('gray'),
        ];
    }

    public function getTabs(): array
        {
            return [
                // 🔴 ABA 1: Foco apenas nas Despesas (Padrão ao abrir a página)
                'saidas_pendentes' => Tab::make('Saídas')
                    ->icon('heroicon-m-arrow-trending-down')
                    ->badge(BankStatement::where('status', 'pending')->where('type', 'debit')->count())
                    ->badgeColor('danger')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->where('type', 'debit')),

                // 🟢 ABA 2: Foco apenas nas Receitas
                'entradas_pendentes' => Tab::make('Entradas ')
                    ->icon('heroicon-m-arrow-trending-up')
                    ->badge(BankStatement::where('status', 'pending')->where('type', 'credit')->count())
                    ->badgeColor('success')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')->where('type', 'credit')),

                // 🗄️ ABA 3: O Histórico
                'historico' => Tab::make('Histórico')
                    ->icon('heroicon-m-archive-box')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', 'pending')),
            ];
        }
}
