<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessClosureRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'cancelled_at' => 'datetime',
        'closed_at' => 'datetime',
        'company_snapshot' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
