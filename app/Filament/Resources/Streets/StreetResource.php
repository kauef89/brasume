<?php
namespace App\Filament\Resources\Streets;
use App\Filament\Resources\Streets\Pages;
use App\Filament\Resources\Streets\Schemas\StreetForm;
use App\Filament\Resources\Streets\Tables\StreetTable;
use App\Models\Street;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StreetResource extends Resource {
    protected static ?string $model = Street::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map';
    protected static string | \UnitEnum | null $navigationGroup = 'Logística';
    protected static ?string $modelLabel = 'Rua';
    protected static ?string $pluralModelLabel = 'Ruas';

    public static function form(Schema $schema): Schema { return StreetForm::configure($schema); }
    public static function table(Table $table): Table { return StreetTable::configure($table); }
    public static function getPages(): array {
        return [
            'index' => Pages\ListStreets::route('/'),
            'create' => Pages\CreateStreet::route('/create'),
            'edit' => Pages\EditStreet::route('/{record}/edit'),
        ];
    }
}