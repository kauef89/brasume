<?php

namespace App\Filament\Resources\LegacyOrders\Pages;

use App\Filament\Resources\LegacyOrders\LegacyOrderResource;
use Filament\Resources\Pages\ManageRecords;

class ManageLegacyOrders extends ManageRecords
{
    protected static string $resource = LegacyOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 🚀 Array vazio! Sem botão de "Novo Pedido".
            // Apenas leitura e ações da tabela serão permitidas.
        ];
    }
}
