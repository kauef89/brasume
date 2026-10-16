<?php

namespace App\Filament\Resources\GoodsReceipts;

use App\Filament\Resources\GoodsReceipts\Pages;
use App\Filament\Resources\GoodsReceipts\Schemas\GoodsReceiptForm;
use App\Filament\Resources\GoodsReceipts\Tables\GoodsReceiptsTable;
use App\Filament\Traits\HasGlobalSearch;
use App\Models\GoodsReceipt;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptResource extends Resource
{
    use HasGlobalSearch;

    protected static ?string $model = GoodsReceipt::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Estoque & Compras';

    // 🚀 Atributo título principal do registro
    protected static ?string $recordTitleAttribute = 'receipt_number';

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Financeiro';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getModelLabel(): string
    {
        return 'Romaneio / Canhoto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Romaneios / Canhotos';
    }

    // 🚀 Define as colunas reais para o Global Search (evita buscar 'name' inexistente)
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'receipt_number',
            'supplier.name',
            'items.product.name',
        ];
    }

    // 🚀 Título exibido no resultado da busca global
    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $num = $record->receipt_number ?: "ID #{$record->id}";
        $supplier = $record->supplier?->name ? " ({$record->supplier->name})" : '';
        return "Romaneio: {$num}{$supplier}";
    }

    // 🚀 Detalhes contextuais no card de resultado
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Fornecedor' => $record->supplier?->name ?? 'Não informado',
            'Data'       => $record->received_at ? $record->received_at->format('d/m/Y') : 'N/D',
            'Status'     => match ($record->status) {
                'pending', 'pending_invoice' => 'Aguardando NF-e',
                'reconciled'                 => 'Conciliado',
                default                      => $record->status,
            },
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return GoodsReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GoodsReceiptsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGoodsReceipts::route('/'),
            'create' => Pages\CreateGoodsReceipt::route('/create'),
            'edit'   => Pages\EditGoodsReceipt::route('/{record}/edit'),
        ];
    }
}
