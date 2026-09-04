<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsEventVenue extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }
}
