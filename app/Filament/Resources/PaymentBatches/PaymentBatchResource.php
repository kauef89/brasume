<?php

namespace App\Filament\Resources\PaymentBatches;

use App\Filament\Resources\PaymentBatches\Pages;
use App\Filament\Resources\PaymentBatches\Tables\PaymentBatchesTable;
use App\Models\PaymentBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class PaymentBatchResource extends Resource
{
    protected static ?string $model = PaymentBatch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';
    protected static ?string $slug = 'lotes-pagamento';
    protected static ?string $modelLabel = 'Lote de Pagamento';
    protected static ?string $pluralModelLabel = 'Lotes Bancários';
    protected static ?int $navigationSort = 15;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function table(Table $table): Table
    {
        return PaymentBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentBatches::route('/'),
        ];
    }
}