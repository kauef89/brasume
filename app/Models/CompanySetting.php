<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    // Liberando o Mass Assignment (ótima escolha para a tabela de configs!)
    protected $guarded = [];

    protected $casts = [
        'certificate_password' => 'encrypted',
        'csc_code' => 'encrypted', // <-- Protegendo o Token da SEFAZ!
        'certificate_expiration' => 'date',
    ];
}