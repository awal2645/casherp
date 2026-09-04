<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyPaymentDepositAllocation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:4'];

    public function deposit()
    {
        return $this->belongsTo(PropertyPaymentDeposit::class, 'property_payment_deposit_id');
    }

    public function rentDue()
    {
        return $this->belongsTo(PropertyRentDue::class, 'property_rent_due_id');
    }
}
