<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\HasTenants;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements HasTenants, FilamentUser
{
    use HasFactory, Notifiable, HasRoles, HasPushSubscriptions;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'pin'               => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getTenants(Panel $panel): array|Collection
    {
        return collect([tenant()]);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return true;
    }

    /**
     * Perfil de RH do colaborador vinculado a esta conta de utilizador
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Pedidos atendidos/lançados por este utilizador
     */
    public function attendedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'employee_id');
    }

    /**
     * Pedidos em que este utilizador atuou como caixa de abertura
     */
    public function cashierOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }

    /**
     * Entregas realizadas por este utilizador
     */
    public function deliveryOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'driver_id');
    }

    public function attendedShifts(): BelongsToMany
    {
        return $this->belongsToMany(CashShift::class, 'shift_attendance')
                    ->withPivot(
                        'checked_in_at',
                        'checked_out_at',
                        'status',
                        'attendance_status',
                        'daily_rate',
                        'assigned_role',
                        'account_payable_id'
                    )
                    ->withTimestamps();
    }

    public function isOnActiveShift(): bool
    {
        return $this->attendedShifts()
                    ->where('cash_shifts.status', 'open')
                    ->wherePivot('status', 'active')
                    ->exists();
    }

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }
}
