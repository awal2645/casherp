<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsGroupBooking extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'arrival_at' => 'datetime',
        'departure_at' => 'datetime',
        'release_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function organizer()
    {
        return $this->belongsTo(\App\Contact::class, 'organizer_contact_id');
    }

    public function roomBlocks()
    {
        return $this->hasMany(HmsGroupRoomBlock::class, 'hms_group_booking_id');
    }

    public function folio()
    {
        return $this->hasOne(HmsFolio::class, 'hms_group_booking_id');
    }
}
