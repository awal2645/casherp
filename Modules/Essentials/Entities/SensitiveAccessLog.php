<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class SensitiveAccessLog extends Model
{
    protected $table = 'hrm_sensitive_access_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new \LogicException('Sensitive access logs are immutable.');
        });
        static::deleting(function () {
            throw new \LogicException('Sensitive access logs are immutable.');
        });
    }
}
