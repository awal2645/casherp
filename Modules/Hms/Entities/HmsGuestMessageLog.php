<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsGuestMessageLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'scheduled_for' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(HmsGuestMessageRule::class, 'hms_guest_message_rule_id');
    }

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }
}
