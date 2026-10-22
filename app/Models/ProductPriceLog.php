<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProductPriceLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'price'               => 'decimal:2',
        'cost'                => 'decimal:4',
        'total_variables'     => 'decimal:2',
        'contribution_margin' => 'decimal:2',
        'margin_percentage'   => 'decimal:2',
        'started_at'          => 'datetime',
        'ended_at'            => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calcula as vendas ocorridas durante a vigência desta condição
     * filtrando estritamente pelo modo de consumo real do item (is_takeaway).
     */
    public function getSalesMetrics(): array
    {
        $start = $this->started_at;
        $end   = $this->ended_at ?? now();

        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $this->product_id)
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereIn('orders.status', ['completed', 'closed', 'delivered', 'paid', 'finished']);

        // 🚀 O CORAÇÃO DA PRECISÃO: Filtra pela flag física do item vendido
        if ($this->channel === 'dine_in') {
            $query->where('order_items.is_takeaway', false);
        } elseif (in_array($this->channel, ['takeaway', 'delivery'])) {
            $query->where('order_items.is_takeaway', true);
        }

        $result = $query->selectRaw("
            COALESCE(SUM(order_items.quantity), 0) as total_qty,
            COALESCE(SUM(order_items.quantity * order_items.unit_price), 0) as total_revenue
        ")->first();

        $totalQty          = (float) ($result->total_qty ?? 0);
        $totalRevenue      = (float) ($result->total_revenue ?? 0);
        $totalCmvCost      = $totalQty * (float) $this->cost;
        $grossContribution = $totalRevenue - $totalCmvCost - ($totalQty * (float) $this->total_variables);

        return [
            'total_qty'          => $totalQty,
            'total_revenue'      => $totalRevenue,
            'total_cmv'          => $totalCmvCost,
            'gross_contribution' => $grossContribution,
        ];
    }
}
