<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsNightAudit extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'business_date' => 'date',
        'summary' => 'array',
        'exceptions' => 'array',
        'override_used' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }
}
