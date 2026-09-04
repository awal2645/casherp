<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class ProductStation extends Model
{
    protected $table = 'restaurant_product_stations';

    protected $guarded = ['id'];

    public function station()
    {
        return $this->belongsTo(KitchenStation::class, 'station_id');
    }
}
