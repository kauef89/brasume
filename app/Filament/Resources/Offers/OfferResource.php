<?php
namespace App\Filament\Resources\Offers;

use App\Filament\Resources\Offers\Pages;
use App\Filament\Resources\Offers\Schemas\OfferForm;
use App\Filament\Resources\Offers\Tables\OfferTable;
use App\Models\Offer;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OfferResource extends Resource {
    protected static ?string $model = Offer::class;
    // protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM';
    protected static ?string $modelLabel = 'Oferta';
    protected static ?string $pluralModelLabel = 'Ofertas';

    public static function form(Schema $schema): Schema { return OfferForm::configure($schema); }
    public static function table(Table $table): Table { return OfferTable::configure($table); }
    public static function getPages(): array {
        return [
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}