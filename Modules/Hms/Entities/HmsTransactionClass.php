<?php
namespace Modules\Hms\Entities;

use App\Transaction;


class HmsTransactionClass extends Transaction
{
    public function hms_booking_lines()
    {
        return $this->hasMany(\Modules\Hms\Entities\HmsBookingLine::class, 'transaction_id', 'id');
    }

    public function hms_booking_extras()
    {
        return $this->hasMany(\Modules\Hms\Entities\HmsBookingExtra::class, 'transaction_id', 'id');    }

    public function hms_booking_events()
    {
        return $this->hasMany(HmsBookingEvent::class, 'transaction_id', 'id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function hms_property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function hms_rate_plan()
    {
        return $this->belongsTo(HmsRatePlan::class, 'hms_rate_plan_id');
    }

    public function hms_group_booking()
    {
        return $this->belongsTo(HmsGroupBooking::class, 'hms_group_booking_id');
    }

    public function hms_guest_profile()
    {
        return $this->belongsTo(HmsGuestProfile::class, 'hms_guest_profile_id');
    }

    public function hms_folios()
    {
        return $this->hasMany(HmsFolio::class, 'transaction_id');
    }

    public function hms_booking_guests()
    {
        return $this->hasMany(HmsBookingGuest::class, 'transaction_id');
    }

    public function hms_room_moves()
    {
        return $this->hasMany(HmsRoomMove::class, 'transaction_id');
    }
}
