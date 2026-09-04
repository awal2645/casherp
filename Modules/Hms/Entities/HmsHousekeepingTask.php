<?php

namespace Modules\Hms\Entities;

use App\Transaction;
use App\User;
use Illuminate\Database\Eloquent\Model;

class HmsHousekeepingTask extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'cleaned_at' => 'datetime',
        'inspected_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(HmsRoom::class, 'hms_room_id');
    }

    public function booking()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function inspectedBy()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
