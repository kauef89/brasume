<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Models\Product;
use App\Services\Catalog\InventoryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditGoodsReceipt extends EditRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record, Actions\DeleteAction $action) {
                    $hasReconciled = $record->items()->where('status', 'reconciled')->exists();
                    if ($hasReconciled) {
                        Notification::make()
                            ->title('Ação Bloqueada')
                            ->body('Este romaneio já possui itens conciliados com uma Nota Fiscal. Não é possível excluí-lo.')
                            ->danger()
                            ->send();
                        $action->cancel();
                        return;
                    }

                    // Estorna o estoque adicionado fisicamente
                    $inventoryService = app(InventoryService::class);
                    foreach ($record->items as $item) {
                        $product = Product::find($item->product_id);
                        if ($product && $product->track_inventory) {
                            $inventoryService->deductStock($product, (float) $item->quantity);
                        }
                    }
                }),
        ];
    }
}
