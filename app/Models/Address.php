<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Customer|null $customer
 * @property-read \App\Models\Neighborhood|null $neighborhood
 * @property-read \App\Models\Street|null $street
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Address query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Address extends Model
{
    use HasFactory;

    protected $guarded = [];

    // 🚀 1. EAGER LOADING: Sempre que buscar um endereço, já traz a rua e o bairro do banco
    protected $with = ['street', 'neighborhood'];

    // 🚀 2. APPENDS: Força o Laravel a injetar essas propriedades virtuais no JSON que vai para o Vue
    protected $appends = ['full_address', 'delivery_fee'];

    protected $casts = [
        'no_number' => 'boolean',
        'is_active' => 'boolean',
    ];

    // 🚀 3. O ACESSOR BLINDADO DO ENDEREÇO
    public function getFullAddressAttribute(): string
    {
        $streetName = $this->street ? $this->street->name : 'Rua não informada';
        $neighborhoodName = $this->neighborhood ? $this->neighborhood->name : 'Bairro não informado';

        $number = $this->no_number ? 'S/N' : ($this->number ?? 'S/N');
        $comp = $this->complement ? " ({$this->complement})" : '';

        return "{$streetName}, {$number} - {$neighborhoodName}{$comp}";
    }

    // 🚀 4. O ACESSOR DA TAXA DE ENTREGA (Puxa direto do relacionamento com o Bairro)
    public function getDeliveryFeeAttribute(): float
    {
        return $this->neighborhood ? (float) $this->neighborhood->delivery_fee : 0.00;
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function street() { return $this->belongsTo(Street::class); }
    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
}
