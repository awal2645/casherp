<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class HmsRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'last_cleaned_at' => 'datetime',
        'last_inspected_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(HmsRoomType::class, 'hms_room_type_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HmsHousekeepingTask::class, 'hms_room_id');
    }

}
