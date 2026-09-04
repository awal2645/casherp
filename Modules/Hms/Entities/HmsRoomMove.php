<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsRoomMove extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['moved_at' => 'datetime'];

    public function fromRoom()
    {
        return $this->belongsTo(HmsRoom::class, 'from_room_id');
    }

    public function toRoom()
    {
        return $this->belongsTo(HmsRoom::class, 'to_room_id');
    }
}
