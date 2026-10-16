<?php

namespace App\Filament\Resources\FiscalDocuments;

use App\Filament\Resources\FiscalDocuments\Pages;
use App\Filament\Resources\FiscalDocuments\Schemas\FiscalDocumentForm;
use App\Filament\Resources\FiscalDocuments\Tables\FiscalDocumentsTable;
use App\Models\FiscalDocument;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class FiscalDocumentResource extends Resource
{
    protected static ?string $model = FiscalDocument::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'Fiscal';
    protected static ?string $modelLabel = 'NFC-e Emitida';
    protected static ?string $pluralModelLabel = 'NFC-es Emitidas';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(FiscalDocumentForm::schema());
    }

    public static function table(Table $table): Table
    {
        return FiscalDocumentsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalDocuments::route('/'),
            'view'  => Pages\ViewFiscalDocument::route('/{record}'),
        ];
    }
}
