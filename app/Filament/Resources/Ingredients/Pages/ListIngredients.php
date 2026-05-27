<?php
namespace App\Filament\Resources\Ingredients\Pages;
use App\Filament\Resources\Ingredients\IngredientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Products\Actions\SmartMergeAction;

class ListIngredients extends ListRecords
{
    protected static string $resource = IngredientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SmartMergeAction::make()->categoryType('internal'),
            CreateAction::make(),
        ];
    }
}
