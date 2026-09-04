<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KitchenStation extends Model
{
    use SoftDeletes;

    protected $table = 'restaurant_kitchen_stations';

    protected $guarded = ['id'];

    protected $casts = ['is_default' => 'boolean', 'is_active' => 'boolean'];

    public function productMappings()
    {
        return $this->hasMany(ProductStation::class, 'station_id');
    }

    public function tickets()
    {
        return $this->hasMany(KitchenTicket::class, 'station_id');
    }
}
