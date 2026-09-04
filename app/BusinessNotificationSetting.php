<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessNotificationSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_enabled' => 'boolean',
        'database_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'recipient_permissions' => 'array',
    ];
}
