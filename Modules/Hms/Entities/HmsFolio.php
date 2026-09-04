<?php

namespace Modules\Hms\Entities;

use Illuminate\Database\Eloquent\Model;

class HmsFolio extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function entries()
    {
        return $this->hasMany(HmsFolioEntry::class, 'hms_folio_id')->orderBy('posted_at')->orderBy('id');
    }

    public function booking()
    {
        return $this->belongsTo(HmsTransactionClass::class, 'transaction_id');
    }

    public function groupBooking()
    {
        return $this->belongsTo(HmsGroupBooking::class, 'hms_group_booking_id');
    }

    public function eventBooking()
    {
        return $this->belongsTo(HmsEventBooking::class, 'hms_event_booking_id');
    }

    public function property()
    {
        return $this->belongsTo(HmsProperty::class, 'hms_property_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_id');
    }

    public function deposits()
    {
        return $this->hasMany(HmsDepositSchedule::class, 'hms_folio_id');
    }

    public function getBalanceAttribute(): float
    {
        $entries = $this->relationLoaded('entries') ? $this->entries : $this->entries()->get();

        return round((float) $entries->whereIn('status', ['posted', 'approved'])->sum(function ($entry) {
            return $entry->direction === 'debit' ? (float) $entry->amount : -(float) $entry->amount;
        }), 4);
    }
}
