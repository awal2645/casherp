<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RegistrationIntent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'reminder_consent' => 'boolean',
        'last_activity_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
