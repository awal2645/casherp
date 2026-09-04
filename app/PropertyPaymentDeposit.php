<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyPaymentDeposit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:4',
        'applied_amount' => 'decimal:4',
        'received_on' => 'date',
    ];

    public function lease()
    {
        return $this->belongsTo(PropertyLease::class, 'property_lease_id');
    }

    public function allocations()
    {
        return $this->hasMany(PropertyPaymentDepositAllocation::class);
    }

    public function getAvailableAmountAttribute(): float
    {
        return round(max(0, (float) $this->amount - (float) $this->applied_amount), 4);
    }
}
