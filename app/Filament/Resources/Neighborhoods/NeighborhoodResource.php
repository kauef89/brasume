<?php
namespace App\Filament\Resources\Neighborhoods;
use App\Filament\Traits\HasGlobalSearch;
use App\Filament\Resources\Neighborhoods\Pages;
use App\Filament\Resources\Neighborhoods\Schemas\NeighborhoodForm;
use App\Filament\Resources\Neighborhoods\Tables\NeighborhoodTable;
use App\Models\Neighborhood;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class NeighborhoodResource extends Resource {
    use HasGlobalSearch;
    protected static ?string $model = Neighborhood::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static string | \UnitEnum | null $navigationGroup = 'Logística';
    protected static ?string $modelLabel = 'Bairro';
    protected static ?string $pluralModelLabel = 'Bairros';

    public static function form(Schema $schema): Schema { return NeighborhoodForm::configure($schema); }
    public static function table(Table $table): Table { return NeighborhoodTable::configure($table); }
    public static function getPages(): array {
        return [
            'index' => Pages\ListNeighborhoods::route('/'),
            'create' => Pages\CreateNeighborhood::route('/create'),
            'edit' => Pages\EditNeighborhood::route('/{record}/edit'),
        ];
    }
}