<?php

namespace App\Models;

use App\Enums\InventoryLossType;
use App\Observers\InventoryLossObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([InventoryLossObserver::class])]
class InventoryLoss extends Model
{
    // Constantes de retrocompatibilidade com o enum InventoryLossType
    public const TYPE_OPERATIONAL      = 'operational';
    public const TYPE_INVENTORY_AUDIT  = 'inventory_audit';
    public const TYPE_EXPIRED          = 'expired';
    public const TYPE_SPOILAGE         = 'spoilage';
    public const TYPE_DAMAGED          = 'damaged';

    protected $guarded = [];

    // 🚀 Flag em memória: impede dedução dupla quando o balanço/ajuste já deduziu o saldo
    public bool $skipInventoryDeduction = false;

    protected function casts(): array
    {
        return [
            'quantity'   => 'decimal:4',
            'unit_cost'  => 'decimal:5',
            'total_cost' => 'decimal:5',
            'loss_type'  => InventoryLossType::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Mantido por retrocompatibilidade caso alguma view antiga ainda acesse $loss->formatted_type
     */
    public function getFormattedTypeAttribute(): string
    {
        return $this->loss_type?->getLabel() ?? 'Desconhecido';
    }
}
