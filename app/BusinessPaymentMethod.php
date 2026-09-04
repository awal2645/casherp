<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessPaymentMethod extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_enabled' => 'boolean', 'accepts_money' => 'boolean'];

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
