<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Filament\Resources\Resource
 */
trait HasGlobalSearch
{
    // Define o título que aparece nos resultados
    public static function getRecordTitleAttribute(): ?string
    {
        return 'name';
    }

    // Define quais colunas do banco de dados serão pesquisadas
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    // A lógica inteligente de roteamento
    public static function getGlobalSearchResultUrl(Model $record): string
    {
        /** 
         * O @mixin lá no topo avisa ao Intelephense que static:: 
         * se refere a métodos de um Filament Resource.
         */

        // Se este recurso tiver uma página "View", manda para lá.
        if (static::hasPage('view')) {
            return static::getUrl('view', ['record' => $record]);
        }

        // Fallback de segurança: se não tiver View, manda para o Edit[cite: 2, 4, 6].
        return static::getUrl('edit', ['record' => $record]);
    }
}
