<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class BookingDetail extends Model
{
    protected $table = 'restaurant_booking_details';

    protected $guarded = ['id'];

    protected $casts = [
        'hold_until' => 'datetime',
        'arrived_at' => 'datetime',
        'seated_at' => 'datetime',
        'completed_at' => 'datetime',
        'expected_spend' => 'decimal:4',
        'payment_deposit_amount' => 'decimal:4',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }
}
