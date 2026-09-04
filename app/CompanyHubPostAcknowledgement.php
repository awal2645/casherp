<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyHubPostAcknowledgement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'opened_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];
}
