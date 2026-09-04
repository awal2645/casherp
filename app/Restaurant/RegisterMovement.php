<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RegisterMovement extends Model
{
    protected $table = 'restaurant_register_movements';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:4'];
}
