<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(NotificationEvent::class, 'notification_event_id');
    }
}
