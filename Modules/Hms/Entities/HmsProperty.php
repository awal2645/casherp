<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HmsProperty extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'business_date' => 'date',
        'booking_engine_enabled' => 'boolean',
        'channel_manager_enabled' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_id');
    }

    public function currency()
    {
        return $this->belongsTo(\App\Currency::class, 'currency_id');
    }

    public function roomTypes()
    {
        return $this->hasMany(HmsRoomType::class, 'hms_property_id');
    }

    public function rooms()
    {
        return $this->hasMany(HmsRoom::class, 'hms_property_id');
    }
}
