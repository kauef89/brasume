<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class DiningTable extends Model
{
    protected $guarded = [];

    public function tableArea(): BelongsTo
    {
        return $this->belongsTo(TableArea::class);
    }

    /**
     * Mesa principal da qual esta mesa faz parte (caso esteja agrupada)
     */
    public function parentTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'parent_table_id');
    }

    /**
     * Mesas secundárias agrupadas a esta mesa principal
     */
    public function linkedTables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'parent_table_id');
    }

    /**
     * Todos os pedidos vinculados a esta mesa
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'dining_table_id');
    }

    /**
     * Pedidos ativos na mesa física direta
     */
    public function activeOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'dining_table_id')
            ->whereNotIn('status', ['closed', 'cancelled', 'completed', 'delivered'])
            ->where('payment_status', '!=', 'paid');
    }

    /**
     * Retorna a coleção de IDs de todas as mesas envolvidas (pai + filhas)
     */
    public function getGroupTableIds(): Collection
    {
        $primary = $this->parent_table_id ? $this->parentTable : $this;

        if (!$primary) {
            return collect([$this->id]);
        }

        return $primary->linkedTables()->pluck('id')->push($primary->id);
    }

    /**
     * Verifica se ainda há qualquer pedido ativo no grupo de mesas
     */
    public function hasActiveOrdersInGroup(?int $excludeOrderId = null): bool
    {
        $ids = $this->getGroupTableIds();

        return Order::whereIn('dining_table_id', $ids)
            ->when($excludeOrderId, fn ($query) => $query->where('id', '!=', $excludeOrderId))
            ->whereNotIn('status', ['closed', 'cancelled', 'completed', 'delivered'])
            ->where('payment_status', '!=', 'paid')
            ->exists();
    }
}
