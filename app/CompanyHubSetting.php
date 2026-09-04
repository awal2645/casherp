<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyHubSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'comments_enabled' => 'boolean',
        'email_important_announcements' => 'boolean',
    ];
}
