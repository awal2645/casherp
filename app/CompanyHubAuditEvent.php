<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyHubAuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
