<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        "base_cost" => "float",
        "dine_in_price" => "float",
        "delivery_price" => "float",
        "promo_price" => "float",
        "conversion_factor" => "float",
        "current_stock" => "float",
        "min_stock" => "float",
        "track_inventory" => "boolean",
        "is_grab_and_go" => "boolean",
        "show_on_menu" => "boolean",
        "show_on_delivery" => "boolean",
        "is_age_restricted" => "boolean",
        "requires_preparation" => "boolean",
        "loyalty_reward" => "boolean",
        "prep_time_minutes" => "integer",
        "serves_people" => "integer",
        "legacy_modifier_groups" => "array",
        "legacy_recipe" => "array",
        "is_monofasico" => "boolean",
        "recipe_yield" => "decimal:4",
    ];

    public function category()
        {
            return $this->belongsTo(Category::class);
        }

        public function modifiers()
    {
        return $this->belongsToMany(ProductModifier::class, 'product_modifier', 'product_id', 'modifier_id')
                    ->withPivot('sort_order');
    }

        public function comboGroups()
        {
            return $this->hasMany(ComboGroup::class);
        }

        public function kitchen()
        {
            return $this->belongsTo(Kitchen::class);
        }

        // 🚀 AQUI ESTÁ A CORREÇÃO! Ensinamos ao Laravel que print_station_id aponta para uma Printer
        public function printer()
        {
            return $this->belongsTo(Printer::class, 'print_station_id');
        }

        public function technicalSheets()
        {
            return $this->hasMany(TechnicalSheet::class, "parent_product_id");
        }

        public function costHistories()
        {
            return $this->hasMany(ProductCostHistory::class);
        }
    }
