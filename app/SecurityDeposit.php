<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SecurityDeposit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_date' => 'date',
        'settled_at' => 'datetime',
        'last_alerted_at' => 'datetime',
        'required_amount' => 'decimal:4',
    ];

    public function entries()
    {
        return $this->hasMany(SecurityDepositEntry::class)->orderBy('occurred_on')->orderBy('id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'business_location_id');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function totalFor(string $type, array $statuses = ['active']): float
    {
        $entries = $this->relationLoaded('entries') ? $this->entries : $this->entries()->get();

        return round((float) $entries->where('entry_type', $type)->whereIn('status', $statuses)->sum('amount'), 4);
    }

    public function getReceivedAmountAttribute(): float
    {
        return $this->totalFor('receipt');
    }

    public function getDamageAmountAttribute(): float
    {
        return $this->totalFor('damage_charge');
    }

    public function getRefundedAmountAttribute(): float
    {
        return $this->totalFor('refund');
    }

    public function getPendingRefundAmountAttribute(): float
    {
        return $this->totalFor('refund', ['pending_approval', 'approved']);
    }

    public function getHeldBalanceAttribute(): float
    {
        return round(max(0, $this->received_amount - $this->damage_amount - $this->refunded_amount), 4);
    }

    public function getAvailableRefundAttribute(): float
    {
        return round(max(0, $this->held_balance - $this->pending_refund_amount), 4);
    }

    public function getExcessDamageAmountAttribute(): float
    {
        return round(max(0, $this->damage_amount - $this->received_amount), 4);
    }
}
