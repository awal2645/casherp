<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class RegisterCount extends Model
{
    protected $table = 'restaurant_register_counts';

    protected $guarded = ['id'];

    protected $casts = ['denomination' => 'decimal:4', 'amount' => 'decimal:4'];
}
