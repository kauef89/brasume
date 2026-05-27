<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyDailyRevenue extends Model
{
    protected $guarded = [];

    // Essencial para converter automaticamente o JSON da base de dados para Arrays no PHP
    protected $casts = [
        "date" => "date",
        "payments_breakdown" => "array",
        "types_breakdown" => "array",
        "total_revenue" => "decimal:2",
        "expenses" => "decimal:2",
    ];
}
