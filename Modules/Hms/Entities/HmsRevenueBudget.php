<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsRevenueBudget extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['month' => 'date'];
}
