<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    // ESCUDO DO CPF: Salva apenas números
    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => preg_replace('/[^0-9]/', '', (string) $value),
        );
    }

    // ESCUDO DO TELEFONE: Salva apenas números
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => preg_replace('/[^0-9]/', '', (string) $value),
        );
    }

    // ==========================================
    // RELACIONAMENTOS (Adicione estas linhas!)
    // ==========================================

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
