<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PropertyDashboardPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visible_widgets' => 'array',
        'widget_order' => 'array',
        'compact_mode' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'default_business_location_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'default_property_id');
    }
}
