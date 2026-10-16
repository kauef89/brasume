<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTask extends Model
{
    protected $guarded = [];
    protected $casts = [
        'target_roles' => 'array',
        'target_users' => 'array',
        'recurrence_days' => 'array',
        'deadline_minutes' => 'integer',
        'deadline_date' => 'datetime',
        'is_inventory_task' => 'boolean',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function logs()
    {
        return $this->hasMany(ChecklistLog::class);
    }

    // 🚀 A RELAÇÃO POLIMÓRFICA DO INVENTÁRIO
    public function inventoryItem()
    {
        return $this->morphTo('inventory_item');
    }
}
