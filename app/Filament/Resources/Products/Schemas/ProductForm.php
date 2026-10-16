<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Enums\UnitOfMeasure;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\Category;
use App\Filament\Resources\Products\Schemas\Sections\BasicSection;
use App\Filament\Resources\Products\Schemas\Sections\PriceAndStockSection;
use App\Filament\Resources\Products\Schemas\Sections\ProductionSection;
use App\Filament\Resources\Products\Schemas\Sections\FiscalSection;
use App\Filament\Resources\Products\Schemas\Sections\ComboSection;
use App\Filament\Resources\Products\Schemas\Sections\WmsSection;

class ProductForm
{
    public static function getFormSchema(): array
    {
        return [
            BasicSection::make(),
            PriceAndStockSection::make(),
            ProductionSection::make(),
            FiscalSection::make(),
            WmsSection::make(),
            ComboSection::make(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->schema(self::getFormSchema());
    }

    /**
     * Formulário enxuto utilizado em modais rápidos (Ex: Importação de NFe)
     */
    public static function getQuickCreateSchema(): array
    {
        return [
            Forms\Components\TextInput::make("name")
                ->label("Nome do Produto")
                ->default(fn(Get $get) => $get("xml_name"))
                ->required(),

            Forms\Components\Select::make("type")
                ->label("Tipo de Produto")
                ->options(ProductType::class)
                ->default("ingredient")
                ->live()
                ->afterStateUpdated(fn(Set $set) => $set("category_id", null))
                ->required(),

            Forms\Components\Select::make("category_id")
                ->label("Categoria")
                ->options(fn (Get $get) => Category::where("type", match ($get("type")) {
                    "resale" => "external",
                    "ingredient" => "internal",
                    "packaging", "consumable" => "supply",
                    default => "internal"
                })->pluck("name", "id"))
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\Select::make("unit_of_measure")
                ->label("Unidade de Medida")
                ->options(UnitOfMeasure::class)
                ->default("UN")
                ->required(),

            Forms\Components\Toggle::make("track_inventory")
                ->label("Controlar Estoque?")
                ->default(true)
                ->inline(false),
        ];
    }
}
