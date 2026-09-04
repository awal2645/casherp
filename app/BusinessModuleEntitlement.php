<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessModuleEntitlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'base_allowance' => 'integer',
        'extra_allowance' => 'integer',
        'amount_paid' => 'decimal:4',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(PremiumModulePlan::class, 'premium_module_plan_id');
    }
}
