<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class IngredientMovement extends Model
{
    protected $table = 'restaurant_ingredient_movements';

    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4'];
}
