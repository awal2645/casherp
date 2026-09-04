<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsRestaurantPosting extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['posted_at' => 'datetime', 'voided_at' => 'datetime'];
}
