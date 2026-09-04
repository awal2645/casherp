<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RecipeLine extends Model
{
    protected $table = 'restaurant_recipe_lines';

    protected $guarded = ['id'];

    protected $casts = ['quantity' => 'decimal:4', 'waste_percentage' => 'decimal:4', 'unit_cost_snapshot' => 'decimal:4'];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }
}
