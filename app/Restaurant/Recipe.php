<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $table = 'restaurant_recipes';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'yield_quantity' => 'decimal:4', 'estimated_cost' => 'decimal:4'];

    public function lines()
    {
        return $this->hasMany(RecipeLine::class, 'recipe_id');
    }
}
