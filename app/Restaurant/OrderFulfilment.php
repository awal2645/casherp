<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class OrderFulfilment extends Model
{
    protected $table = 'restaurant_order_fulfilments';

    protected $guarded = ['id'];

    protected $casts = [
        'promised_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'ready_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivery_address' => 'array',
        'metadata' => 'array',
    ];
}
