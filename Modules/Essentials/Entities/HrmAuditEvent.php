<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmAuditEvent extends Model
{
    protected $table = 'hrm_audit_events';

    protected $guarded = ['id'];

    protected $casts = [
        'previous_values' => 'array',
        'new_values' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new \LogicException('HR audit events are immutable.');
        });
        static::deleting(function () {
            throw new \LogicException('HR audit events are immutable.');
        });
    }
}
