<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsOperationalTask extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['occurred_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];

    public function room()
    {
        return $this->belongsTo(HmsRoom::class, 'hms_room_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(\App\User::class, 'assigned_to');
    }

    public function events()
    {
        return $this->hasMany(HmsOperationalEvent::class, 'hms_operational_task_id')->orderBy('occurred_at');
    }
}
