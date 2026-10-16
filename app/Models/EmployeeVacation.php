<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Employee|null $employee
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeVacation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeVacation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeVacation query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class EmployeeVacation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
