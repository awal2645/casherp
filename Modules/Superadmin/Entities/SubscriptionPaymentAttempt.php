<?php

namespace Modules\Superadmin\Entities;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPaymentAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:4',
        'package_snapshot' => 'array',
        'provider_response' => 'array',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
