<?php

namespace App\Filament\Resources\AcquirerTransactions\Pages;

use App\Enums\AcquirerMatchStatus;
use App\Filament\Resources\AccountReceivables\Actions\ImportAcquirerReportAction;
use App\Filament\Resources\AcquirerTransactions\AcquirerTransactionResource;
use App\Filament\Traits\TeleportsTabs;
use App\Models\AcquirerTransaction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageAcquirerTransactions extends ManageRecords
{
    use TeleportsTabs;

    protected static string $resource = AcquirerTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAcquirerReportAction::make(),

            \Filament\Actions\Action::make('reprocess_pending')
                ->label('Reprocessar Pendências')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Reprocessar Conciliação Automática')
                ->modalDescription('O sistema reavaliará todas as transações com dúvida ou órfãs utilizando as regras e vínculos de terminais atualizados.')
                ->action(function () {
                    $engine = app(\App\Services\Financial\Acquirers\AcquirerReconciliationEngine::class);
                    $promoted = $engine->reprocessPending();

                    \Filament\Notifications\Notification::make()
                        ->title('Reprocessamento Concluído')
                        ->body("{$promoted} transação(ões) foram conciliadas automaticamente.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $ambiguousCount = AcquirerTransaction::where('status', AcquirerMatchStatus::AMBIGUOUS->value)->count();
        $orphanCount    = AcquirerTransaction::where('status', AcquirerMatchStatus::ORPHAN->value)->count();

        return [
            'pendencias' => Tab::make('Dúvidas e Órfãs')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge($ambiguousCount + $orphanCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    AcquirerMatchStatus::AMBIGUOUS->value,
                    AcquirerMatchStatus::ORPHAN->value,
                ])),

            'duvidas' => Tab::make('Dúvidas (Ambíguas)')
                ->icon('heroicon-m-question-mark-circle')
                ->badge($ambiguousCount ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AcquirerMatchStatus::AMBIGUOUS->value)),

            'orfas' => Tab::make('Vendas Órfãs')
                ->icon('heroicon-m-receipt-refund')
                ->badge($orphanCount ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AcquirerMatchStatus::ORPHAN->value)),

            'conciliadas' => Tab::make('Conciliadas')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AcquirerMatchStatus::MATCHED->value)),

            'todas' => Tab::make('Todas as Vendas')
                ->icon('heroicon-m-queue-list'),
        ];
    }
}
