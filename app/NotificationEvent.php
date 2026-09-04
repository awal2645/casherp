<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
