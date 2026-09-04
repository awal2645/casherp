<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HmsBookingLine extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function transaction()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function room()
    {
        return $this->belongsTo(HmsRoom::class, 'hms_room_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }
}
