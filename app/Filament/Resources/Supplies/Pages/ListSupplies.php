<?php
namespace App\Filament\Resources\Supplies\Pages;
use App\Filament\Resources\Supplies\SupplyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\Products\Actions\SmartMergeAction;

class ListSupplies extends ListRecords
{
    protected static string $resource = SupplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 E agora a mágica também funciona aqui!
            SmartMergeAction::make()->categoryType('supply'),
            CreateAction::make(),
        ];
    }
}
