<?php

namespace App\Filament\Resources\AcquirerTransactions;

use App\Enums\AcquirerMatchStatus;
use App\Filament\Resources\AcquirerTransactions\Pages\ManageAcquirerTransactions;
use App\Filament\Resources\AcquirerTransactions\Tables\AcquirerTransactionsTable;
use App\Models\AcquirerTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class AcquirerTransactionResource extends Resource
{
    protected static ?string $model = AcquirerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';
    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Auditoria de Maquininha';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Auditoria de Maquininhas';
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = AcquirerTransaction::query()
            ->whereIn('status', [AcquirerMatchStatus::AMBIGUOUS, AcquirerMatchStatus::ORPHAN])
            ->count();

        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return AcquirerTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAcquirerTransactions::route('/'),
        ];
    }
}
