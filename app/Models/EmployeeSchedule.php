<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSchedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'is_day_off' => 'boolean',
        'is_extra_shift' => 'boolean',
        'schedule_slot_id' => 'integer',
        'employee_id' => 'integer',
        'payable_id' => 'integer',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class, 'payable_id');
    }
}
