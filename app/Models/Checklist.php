<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'recurrence_days' => 'array',
        // Se usar campos JSON no futuro para configurações flexíveis, adicione aqui
    ];

    /**
     * Traz as tarefas respeitando a ordem definida no painel
     */
    public function tasks()
    {
        return $this->hasMany(ChecklistTask::class)->orderBy('sort_order');
    }
}
