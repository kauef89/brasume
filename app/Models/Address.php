<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['street', 'neighborhood'];

    protected $appends = ['full_address', 'delivery_fee', 'coordinates'];

    protected $casts = [
        'no_number' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Mutator: Normaliza qualquer link do Google Maps, WhatsApp ou coordenadas brutas
     * para o formato canônico: https://www.google.com/maps?q=latitude,longitude
     */
    public function setLocationAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['location'] = null;
            return;
        }

        $val = trim((string) $value);
        $unquoted = urldecode($val);

        if (preg_match('#(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)#', $unquoted, $matches)) {
            $url = "https://maps.google.com/maps?q={$matches[1]},{$matches[2]}&z=17&hl=pt-BR";
            $this->attributes['location'] = $url;
            if (empty($this->attributes['location_link'])) {
                $this->attributes['location_link'] = $url;
            }
        } else {
            $this->attributes['location'] = $val;
        }
    }

    /**
     * Acessor: Extrai apenas "latitude,longitude" para traçar rotas e waypoints diretos
     */
    public function getCoordinatesAttribute(): ?string
    {
        $raw = $this->location ?: $this->location_link;
        if (!$raw) return null;

        if (preg_match('#(-?\d{1,2}\.\d+)\s*[,/]\s*(-?\d{1,3}\.\d+)#', urldecode($raw), $matches)) {
            return "{$matches[1]},{$matches[2]}";
        }

        return null;
    }

    public function getFullAddressAttribute(): string
    {
        $streetName = $this->street ? $this->street->name : 'Rua não informada';
        $neighborhoodName = $this->neighborhood ? $this->neighborhood->name : 'Bairro não informado';

        $number = $this->no_number ? 'S/N' : ($this->number ?? 'S/N');
        $comp = $this->complement ? " ({$this->complement})" : '';

        return "{$streetName}, {$number} - {$neighborhoodName}{$comp}";
    }

    public function getDeliveryFeeAttribute(): float
    {
        return $this->neighborhood ? (float) $this->neighborhood->delivery_fee : 0.00;
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function street() { return $this->belongsTo(Street::class); }
    public function neighborhood() { return $this->belongsTo(Neighborhood::class); }
}
