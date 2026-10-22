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
        "is_grab_and_go"         => "boolean",
        "show_on_menu"           => "boolean",
        "show_on_delivery"       => "boolean",
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
        return $this->belongsToMany(StorageLocation::class, 'product_storage_location', 'product_id', 'storage_location_id')
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
}
