<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class OrderEvent extends Model
{
    protected $table = 'restaurant_order_events';

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array'];
}
