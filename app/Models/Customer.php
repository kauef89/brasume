<?php

namespace App\Models;

use App\Observers\CustomerObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

#[ObservedBy([CustomerObserver::class])]
class Customer extends Model
{
    use Notifiable, HasPushSubscriptions;
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => blank($value) ? null : preg_replace('/[^0-9]/', '', $value),
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => blank($value) ? null : preg_replace('/[^0-9]/', '', $value),
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    // 🏢 CONVÉNIO: Empresa / Fornecedor conveniado ao qual o cliente pertence
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    // 👷 RH: Cadastro de funcionário correspondente ao cliente
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function pushSubscriptions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(PushSubscription::class, 'subscribable');
    }
}
