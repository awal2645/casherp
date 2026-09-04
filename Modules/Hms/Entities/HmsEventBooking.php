<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsEventBooking extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'agreed_amount' => 'decimal:4',
        'payment_deposit_required' => 'decimal:4',
    ];

    public function venue()
    {
        return $this->belongsTo(HmsEventVenue::class, 'hms_event_venue_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function folio()
    {
        return $this->hasOne(HmsFolio::class, 'hms_event_booking_id');
    }

    public function charges()
    {
        return $this->hasMany(HmsEventCharge::class, 'hms_event_booking_id');
    }
}
