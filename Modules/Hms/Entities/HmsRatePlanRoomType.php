<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsRatePlanRoomType extends Model
{
    protected $guarded = ['id'];

    public function ratePlan()
    {
        return $this->belongsTo(HmsRatePlan::class, 'hms_rate_plan_id');
    }

    public function roomType()
    {
        return $this->belongsTo(HmsRoomType::class, 'hms_room_type_id');
    }
}
