<?php

namespace App\Models;

use App\Events\SettingsUpdated;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'customer_auth_methods'      => 'array',
        'kitchen_separate_multiples' => 'boolean',
        'is_fiscal_emitter'          => 'boolean',
        'require_whatsapp'           => 'boolean',
        'require_birth_date'         => 'boolean',
        'allow_minors'               => 'boolean',
        'customer_can_edit_profile'  => 'boolean',
        'has_service_tax'            => 'boolean',
        'service_tax_percentage'     => 'decimal:2',
        'service_tax_table'          => 'boolean',
        'service_tax_counter'        => 'boolean',
        'service_tax_delivery'       => 'boolean',
        'require_shift_attendance'   => 'boolean',
        'loyalty_is_active'          => 'boolean',
        'pos_secure_actions'         => 'array',
        'customer_allow_guest'       => 'boolean',
        'print_bridge_connected_at'  => 'datetime',
        'sefaz_last_sync_at'         => 'datetime',
        'enforce_cashier_open'          => 'boolean',
        'enforce_cashier_open_customer' => 'boolean',
        'enforce_cashier_open_team'     => 'boolean',
        'allow_pre_orders'              => 'boolean',
        'pre_order_buffer_minutes'   => 'integer',
        'business_hours'             => 'array',
        'is_24_hours'                => 'boolean',
        'is_closed_indefinitely'     => 'boolean',
        'home_bg_open'               => 'array',
        'home_bg_closed'             => 'array',
        'has_food_allowance'         => 'boolean',
        'self_service_enabled'       => 'boolean',
        'allow_table_self_service'   => 'boolean',
        'attention_categories'       => 'array',
        'cashier_auth_methods'       => 'array',
        'kds_auth_methods'           => 'array',
        'request_order_rating'       => 'boolean',
        'loyalty_max_redeem_value'   => 'decimal:2',
        'loyalty_max_redeem_type'    => 'string',
        'customer_can_append_orders' => 'boolean',
        'print_loyalty_flyer'        => 'boolean',
        'print_growth_flyer'         => 'boolean',
        'growth_flyer_value'         => 'decimal:2',
        'suppress_bar_print_tables' => 'boolean',
        'sicredi_is_active'          => 'boolean',
        'sicredi_enable_driver_pix'  => 'boolean',
        'sicredi_enable_pos_pix'     => 'boolean',
        'sicredi_enable_waiter_pix'  => 'boolean',
        'team_order_channels'        => 'array',
        'team_counter_mode'          => 'string',
    ];

    /**
     * Sanitização automática do domínio personalizado.
     */
    protected function customDomain(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value
                ? strtolower(trim(preg_replace('#^https?://#i', '', rtrim($value, '/'))))
                : null
        );
    }

    /**
     * Retorna a URL base do tenant priorizando o domínio próprio configurado.
     */
    public function getBaseUrl(?string $path = null): string
    {
        $base = !empty($this->custom_domain)
            ? 'https://' . $this->custom_domain
            : rtrim(url('/'), '/');

        if ($path) {
            return $base . '/' . ltrim($path, '/');
        }

        return $base;
    }

    /**
     * Retorna a URL amigável do tenant para avaliação no Google.
     */
    public function getReviewUrl(): ?string
    {
        if (empty($this->google_review_link)) {
            return null;
        }

        return $this->getBaseUrl('avaliacao');
    }

    public function isServiceTaxEnabledFor(?string $channel): bool
    {
        if (!$this->has_service_tax) {
            return false;
        }

        return match ($channel) {
            'table'                         => (bool) ($this->service_tax_table ?? true),
            'counter', 'takeaway', 'pickup' => (bool) ($this->service_tax_counter ?? false),
            'delivery'                      => (bool) ($this->service_tax_delivery ?? false),
            default                         => false,
        };
    }

    public function calculateServiceFee(float $subtotal, ?string $channel): float
    {
        if (!$this->isServiceTaxEnabledFor($channel)) {
            return 0.00;
        }

        $percentage = (float) ($this->service_tax_percentage ?? 0);
        if ($percentage <= 0) {
            return 0.00;
        }

        return round(($subtotal * $percentage) / 100, 2);
    }

    public static function getCached(): ?self
    {
        if (!function_exists('tenant') || !tenant('id')) {
            return null;
        }

        $tenantId = tenant('id');
        $cacheKey = "company_settings_{$tenantId}";

        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                return Cache::tags(['settings'])->rememberForever($cacheKey, fn () => self::first());
            }
        } catch (\Throwable $e) {}

        return Cache::rememberForever($cacheKey, fn () => self::first());
    }

    public static function purgeCache(?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? (function_exists('tenant') && tenant('id') ? tenant('id') : 'central');
        $cacheKey = "company_settings_{$tenantId}";

        try {
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['settings'])->flush();
            }
        } catch (\Throwable $e) {}

        Cache::forget($cacheKey);

        try {
            event(new SettingsUpdated($tenantId));
        } catch (\Throwable $e) {}
    }

    
    public function getTeamOrderChannelsAttribute($value)
    {
        if (empty($value)) {
            return ['table', 'counter'];
        }
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) && !empty($decoded) ? $decoded : ['table', 'counter'];
    }

    public function getTeamCounterModeAttribute($value)
    {
        return $value ?: 'simple';
    }

    protected static function booted(): void
    {
        static::saved(function ($settings) {
            // 1. Limpeza de Cache da Loja
            self::purgeCache();

            // 2. 🚀 AUTOMATIZAÇÃO DE MÉTODOS DE PAGAMENTO RH

            // Vale-Alimentação (Cortesia)
            $allowanceMethod = \App\Models\PaymentMethod::firstOrCreate(
                ['engine_type' => 'allowance'],
                ['name' => 'Vale-Alimentação', 'is_active' => (bool) $settings->has_food_allowance]
            );
            if (!$allowanceMethod->wasRecentlyCreated && (bool) $allowanceMethod->is_active !== (bool) $settings->has_food_allowance) {
                $allowanceMethod->update(['is_active' => (bool) $settings->has_food_allowance]);
            }

            // Fiado (Consumo Interno / Desconto)
            $tabMethod = \App\Models\PaymentMethod::firstOrCreate(
                ['engine_type' => 'internal'],
                ['name' => 'Consumo Interno (Fiado)', 'is_active' => (bool) $settings->allow_employee_tab]
            );
            if (!$tabMethod->wasRecentlyCreated && (bool) $tabMethod->is_active !== (bool) $settings->allow_employee_tab) {
                $tabMethod->update(['is_active' => (bool) $settings->allow_employee_tab]);
            }
        });

        static::deleted(function () {
            self::purgeCache();
        });
    }
}
