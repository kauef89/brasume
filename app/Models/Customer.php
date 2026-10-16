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

#[ObservedBy([CustomerObserver::class])] // 🚀 AQUI: Conectando o Observer ao Model!
class Customer extends Model
{
    use Notifiable, HasPushSubscriptions;
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function cpf(): Attribute
    {
        return Attribute::make(
            // 🚀 CORREÇÃO: Se estiver vazio, devolve null puro para o banco não reclamar do Unique!
            set: fn (?string $value) => blank($value) ? null : preg_replace('/[^0-9]/', '', $value),
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            // 🚀 CORREÇÃO APLICADA AO TELEFONE TAMBÉM POR SEGURANÇA
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

    // 🚀 A LIGAÇÃO NOVA: Cupons exclusivos vinculados a este cliente (Aniversário, etc)
    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }
}
