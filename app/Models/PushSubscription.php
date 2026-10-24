<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';
    protected $guarded = [];

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}