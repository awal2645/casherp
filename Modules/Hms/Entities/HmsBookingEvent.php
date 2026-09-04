<?php

namespace Modules\Hms\Entities;

use App\User;
use Illuminate\Database\Eloquent\Model;

class HmsBookingEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
