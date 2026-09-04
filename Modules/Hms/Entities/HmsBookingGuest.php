<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsBookingGuest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function bookingLine()
    {
        return $this->belongsTo(HmsBookingLine::class, 'hms_booking_line_id');
    }
}
