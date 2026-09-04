<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class WaiterRequest extends Model
{
    protected $table = 'restaurant_waiter_requests';

    protected $guarded = ['id'];

    protected $casts = ['acknowledged_at' => 'datetime', 'resolved_at' => 'datetime'];
}
