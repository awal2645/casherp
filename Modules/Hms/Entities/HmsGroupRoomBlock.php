<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsGroupRoomBlock extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['release_at' => 'datetime'];

    public function group()
    {
        return $this->belongsTo(HmsGroupBooking::class, 'hms_group_booking_id');
    }

    public function room()
    {
        return $this->belongsTo(HmsRoom::class, 'hms_room_id');
    }

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }
}
