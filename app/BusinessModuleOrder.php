<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessModuleOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'increments' => 'integer',
        'allowance_quantity' => 'integer',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(PremiumModulePlan::class, 'premium_module_plan_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
