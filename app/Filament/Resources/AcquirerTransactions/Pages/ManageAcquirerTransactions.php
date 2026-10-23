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
            ImportAcquirerReportAction::make()
                ->label('Importar Relatório CSV')
                ->color('success'),
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
