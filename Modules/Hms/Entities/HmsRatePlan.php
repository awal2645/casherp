<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsRatePlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'adjustment_value' => 'decimal:4',
        'deposit_percent' => 'decimal:4',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'days_of_week' => 'array',
        'closed_to_arrival' => 'boolean',
        'closed_to_departure' => 'boolean',
        'is_refundable' => 'boolean',
        'tax_inclusive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function roomTypes()
    {
        return $this->hasMany(HmsRatePlanRoomType::class, 'hms_rate_plan_id');
    }
}
