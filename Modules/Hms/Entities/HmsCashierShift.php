<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsCashierShift extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['business_date' => 'date', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }
}
