<?php

namespace App\Filament\Resources\Printers; // <-- Agora ele mora DENTRO da pasta Printers!

use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Printers\Pages;
use App\Filament\Resources\Printers\Schemas\PrinterForm;
use App\Filament\Resources\Printers\Tables\PrinterTable;
use App\Models\Printer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PrinterResource extends Resource
{
    use HasGlobalSearch;
    protected static ?string $model = Printer::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-printer';
    protected static string | \UnitEnum | null $navigationGroup = 'PDV & Infraestrutura';
    protected static ?string $modelLabel = 'Impressora';
    protected static ?string $pluralModelLabel = 'Impressoras';

    public static function form(Schema $schema): Schema
    {
        return PrinterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrinterTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrinters::route('/'),
            'create' => Pages\CreatePrinter::route('/create'),
            'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}