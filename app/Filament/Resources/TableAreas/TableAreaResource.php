<?php

namespace App\Filament\Resources\TableAreas;

use App\Filament\Resources\TableAreas\Pages;
use App\Models\TableArea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

// Nossos arquivos componentizados
use App\Filament\Resources\TableAreas\Schemas\TableAreaForm;
use App\Filament\Resources\TableAreas\Tables\TableAreasTable;

class TableAreaResource extends Resource
{
    protected static ?string $model = TableArea::class;

    protected static string|\UnitEnum|null $navigationGroup = "Atendimento";
    protected static ?string $modelLabel = "Área e Mesas";
    protected static ?string $pluralModelLabel = "Mapa de Mesas";

    // Delega o Form
    public static function form(Schema $schema): Schema
    {
        return TableAreaForm::configure($schema);
    }

    // Delega a Table
    public static function table(Table $table): Table
    {
        return TableAreasTable::build($table);
    }

    public static function getPages(): array
    {
        return [
            "index" => Pages\ManageTableAreas::route("/"),
        ];
    }
}
