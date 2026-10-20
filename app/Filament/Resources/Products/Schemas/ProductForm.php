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
                ->default(ProductType::INGREDIENT->value)
                ->live()
                ->afterStateUpdated(fn(Set $set) => $set("category_id", null))
                ->required(),

            Forms\Components\Select::make("category_id")
                ->label("Categoria")
                ->options(function (Get $get) {
                    $rawType = $get("type");

                    if (!$rawType) {
                        return [];
                    }

                    // 🚀 Normalização segura: aceita tanto Enum quanto String
                    $enumType = $rawType instanceof \App\Enums\ProductType
                        ? $rawType
                        : \App\Enums\ProductType::tryFrom((string) $rawType);

                    // Puxa 'external', 'internal' ou 'supply' via método de domínio
                    $categoryGroup = $enumType?->defaultCategoryType() ?? 'internal';

                    return Category::query()
                        ->where("type", $categoryGroup)
                        ->orderBy("name")
                        ->pluck("name", "id");
                })
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
                ->default(true),
        ];
    }
}
