<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SimulatorRelationManager extends RelationManager
{
    // Usamos 'modifiers' apenas para satisfazer a regra do Filament de precisar de uma relação,
    // mas nós vamos ignorar a tabela completamente e carregar o Widget.
    protected static string $relationship = 'modifiers';
    protected static ?string $title = 'Simulador';
    protected static string|\BackedEnum|null $icon = 'heroicon-o-sparkles';

    public function table(Table $table): Table
    {
        return $table
            ->columns([]) // Deixamos as colunas vazias
            ->paginated(false)
            ->content(fn () => view('filament.resources.products.widgets.simulator-wrapper', [
                'record' => $this->getOwnerRecord(),
            ]));
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // A aba do Simulador SÓ VAI APARECER se o produto tiver modificadores cadastrados!
        return $ownerRecord->modifiers()->whereIn('type', ['add_on', 'ingredient'])->exists();
    }
}
