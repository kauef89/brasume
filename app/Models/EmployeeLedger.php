<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read \App\Models\Employee|null $employee
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLedger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLedger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmployeeLedger query()
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class EmployeeLedger extends Model
{
    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
