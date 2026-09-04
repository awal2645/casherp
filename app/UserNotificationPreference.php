<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'database_enabled' => 'boolean',
        'email_enabled' => 'boolean',
    ];
}
