<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
        {
            return [
                // 🚀 O BOTÃO DE ACESSO À ÁRVORE
                Actions\Action::make('reorganize')
                    ->label('Reorganizar Hierarquia')
                    ->icon('heroicon-o-queue-list')
                    ->color('info')
                    ->url(fn () => static::getResource()::getUrl('tree')),

                Actions\CreateAction::make(),
            ];
        }
}
