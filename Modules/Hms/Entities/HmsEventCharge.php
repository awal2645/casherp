<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsEventCharge extends Model
{
    protected $guarded = ['id'];

    public function eventBooking()
    {
        return $this->belongsTo(HmsEventBooking::class, 'hms_event_booking_id');
    }
}
