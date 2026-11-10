<?php

namespace App\Models;

use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "base_cost"              => "float",
        "dine_in_price"          => "float",
        "delivery_price"         => "float",
        "promo_price"            => "float",
        "conversion_factor"      => "float",
        "current_stock"          => "float",
        "min_stock"              => "float",
        "max_stock"              => "float",
        "track_inventory"        => "boolean",
        "track_consumption"      => "boolean",
        "consumption_cycle_days" => "integer",
        "is_grab_and_go"         => "boolean",
        "show_on_menu"           => "boolean",
        "show_on_customer_app"       => "boolean",
        "is_age_restricted"      => "boolean",
        "requires_preparation"   => "boolean",
        "loyalty_reward"         => "boolean",
        "prep_time_minutes"      => "integer",
        "serves_people"          => "integer",
        "legacy_modifier_groups" => "array",
        "legacy_recipe"          => "array",
        "is_monofasico"          => "boolean",
        "recipe_yield"           => "decimal:4",
    ];

    protected $appends = ['image_url', 'unit_measure', 'purchase_pack_info'];

    public function getUnitMeasureAttribute(): ?string
    {
        return strtolower($this->unit_of_measure ?? 'un');
    }

    public function setUnitMeasureAttribute($value): void
    {
        $this->attributes['unit_of_measure'] = !empty($value) ? strtoupper($value) : 'UN';
    }

    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }

    public function getPurchasePackInfoAttribute(): array
    {
        return $this->resolvePurchasePackaging();
    }

    public function resolvePurchasePackaging(): array
    {
        $factor = (float) ($this->conversion_factor > 0 ? $this->conversion_factor : 1);
        $purchaseUom = $this->purchase_unit_of_measure;

        if (!$purchaseUom || $factor <= 1) {
            $latestPurchase = null;

            if ($this->relationLoaded('latestCostHistory')) {
                $latestPurchase = $this->latestCostHistory;
            } elseif ($this->relationLoaded('costHistories')) {
                $latestPurchase = $this->costHistories->sortByDesc('id')->first();
            } else {
                $latestPurchase = $this->latestCostHistory()->first();
            }

            if ($latestPurchase) {
                if ($latestPurchase->conversion_factor > 1) {
                    $factor = (float) $latestPurchase->conversion_factor;
                }
                if (!empty($latestPurchase->original_unit_of_measure)) {
                    $purchaseUom = strtoupper($latestPurchase->original_unit_of_measure);
                }
            }
        }

        return [
            'factor'       => $factor,
            'purchase_uom' => $purchaseUom ?: strtoupper($this->unit_of_measure ?? 'UN'),
            'base_uom'     => strtoupper($this->unit_of_measure ?? 'UN'),
            'is_pack'      => $factor > 1,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(ProductModifier::class, 'modifier_group_product', 'product_id', 'modifier_group_id')
                    ->withPivot('excluded_options')
                    ->withTimestamps();
    }

    public function comboGroups(): HasMany
    {
        return $this->hasMany(ComboGroup::class);
    }

    public function kitchen(): BelongsTo
    {
        return $this->belongsTo(Kitchen::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class, 'print_station_id');
    }

    public function technicalSheets(): HasMany
    {
        return $this->hasMany(TechnicalSheet::class, "parent_product_id");
    }

    public function costHistories(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class);
    }

    public function latestCostHistory(): HasOne
    {
        return $this->hasOne(ProductCostHistory::class)->latestOfMany('id');
    }

    public function storageRules(): HasMany
    {
        return $this->hasMany(ProductStorageLocation::class);
    }

    public function storageLocations(): BelongsToMany
    {
        return $this->belongsToMany(StorageLocation::class, 'product_storage_locations', 'product_id', 'storage_location_id')
                    ->withPivot('current_stock', 'max_capacity', 'restock_threshold', 'source_location_id')
                    ->withTimestamps();
    }

    // 🚀 Extrato Contábil de Estoque (Kardex Ledger)
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('id');
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(ProductPriceLog::class)->orderBy('started_at', 'desc');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active_catalog', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (function_exists('filament') && filament()->getCurrentPanel() !== null) {
                return;
            }

            $builder->where('status', '!=', 'archived');

            $builder->where(function ($query) {
                $query->whereHas('category', function ($q) {
                    $q->where('is_active', true);
                })->orWhereNull('category_id');
            });
        });
    }

    /**
     * Calcula o intervalo médio (em dias) entre as últimas compras registradas.
     */
    public function calculateAveragePurchaseCycleDays(): ?float
    {
        $dates = $this->costHistories()
            ->whereNotNull('created_at')
            ->orderBy('created_at', 'asc')
            ->pluck('created_at')
            ->map(fn($date) => \Carbon\Carbon::parse($date));

        if ($dates->count() < 2) {
            return null;
        }

        $intervals = [];
        for ($i = 1; $i < $dates->count(); $i++) {
            $diff = $dates[$i - 1]->diffInDays($dates[$i]);
            if ($diff > 0) {
                $intervals[] = $diff;
            }
        }

        return count($intervals) > 0 ? round(array_sum($intervals) / count($intervals), 1) : null;
    }

    /**
     * Obtém o ciclo efetivo de reposição: valor manual arbitrado ou média histórica.
     */
    public function getEffectiveConsumptionCycleDays(): ?float
    {
        if (!is_null($this->consumption_cycle_days) && $this->consumption_cycle_days > 0) {
            return (float) $this->consumption_cycle_days;
        }

        return $this->calculateAveragePurchaseCycleDays();
    }

    /**
     * Avalia se o item atingiu a janela de reabastecimento por consumo.
     */
    public function getConsumptionReplenishmentStatus(): array
    {
        $cycleDays = $this->getEffectiveConsumptionCycleDays();
        $latest = $this->latestCostHistory ?? $this->costHistories()->latest('id')->first();

        if (!$latest || !$cycleDays || $cycleDays <= 0) {
            return [
                'needs_restock'            => false,
                'cycle_days'               => $cycleDays,
                'days_since_last'          => null,
                'days_remaining'           => null,
                'projected_depletion_date' => null,
            ];
        }

        $lastDate = \Carbon\Carbon::parse($latest->created_at);
        $daysSince = (int) $lastDate->diffInDays(now());
        $depletionDate = $lastDate->copy()->addDays((int) round($cycleDays));
        $daysRemaining = (int) now()->diffInDays($depletionDate, false);

        return [
            'needs_restock'            => $daysRemaining <= 3, // Dispara sugestão 3 dias antes do fim estimado
            'cycle_days'               => $cycleDays,
            'days_since_last'          => $daysSince,
            'days_remaining'           => $daysRemaining,
            'last_purchase_date'       => $lastDate->format('d/m/Y'),
            'projected_depletion_date' => $depletionDate->format('d/m/Y'),
            'suggested_quantity'       => (float) ($latest->quantity > 0 ? $latest->quantity : 1),
        ];
    }

    public function getCostChainEvaluationAttribute(): array
    {
        return \App\Services\Catalog\ProductCostingService::evaluateCostChain($this);
    }

    public function getCostChainStatusAttribute(): \App\Enums\CostChainStatus
    {
        return $this->cost_chain_evaluation['status'];
    }
}
