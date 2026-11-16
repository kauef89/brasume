<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 * @property-read \App\Models\Order|null $order
 */
class FiscalDocument extends Model
{
    protected $guarded = [];

    protected $hidden = ['xml_content'];

    protected $casts = [
        'issue_date' => 'datetime',
        'total_value' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getProtocolAttribute(?string $value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if (!empty($this->xml_content)
            && preg_match('/<nProt>([^<]+)<\/nProt>/', $this->xml_content, $m)
        ) {
            return trim($m[1]);
        }

        return !empty($this->import_tag) ? 'Importado DFE' : null;
    }
}
