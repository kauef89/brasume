<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistLog extends Model
{
    protected $guarded = [];

    public function task()
    {
        return $this->belongsTo(ChecklistTask::class, 'checklist_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
