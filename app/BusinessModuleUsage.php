<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessModuleUsage extends Model
{
    protected $table = 'business_module_usage';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'used_quantity' => 'integer',
    ];
}
