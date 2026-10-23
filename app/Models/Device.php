<?php

namespace App\Models;

use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Device extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type'           => DeviceType::class,
        'is_active'      => 'boolean',
        'last_active_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Garante UUID único em segundo plano para maquininhas que não exibem o campo
        static::creating(function (Device $device) {
            if (empty($device->device_uuid)) {
                $device->device_uuid = (string) Str::uuid();
            }
        });
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    public function scopePaymentTerminals($query)
    {
        return $query->whereIn('type', [
            DeviceType::SMART_POS,
            DeviceType::POS_PINPAD,
        ]);
    }

    public function scopeAppTerminals($query)
    {
        return $query->whereIn('type', [
            DeviceType::POS,
            DeviceType::KDS,
            DeviceType::TOTEM,
            DeviceType::WAITER,
        ]);
    }
}
