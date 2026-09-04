<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RestaurantOperationSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'enabled_channels' => 'array',
        'reservation_policy' => 'array',
        'kitchen_policy' => 'array',
        'inventory_policy' => 'array',
        'register_policy' => 'array',
        'is_active' => 'boolean',
    ];
}
