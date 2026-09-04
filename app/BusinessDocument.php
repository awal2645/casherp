<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessDocument extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'valid_until' => 'date',
        'service_start_at' => 'datetime',
        'service_end_at' => 'datetime',
        'business_snapshot' => 'array',
        'party_snapshot' => 'array',
        'template_snapshot' => 'array',
        'data' => 'array',
        'share_expires_at' => 'datetime',
        'issued_at' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_document_id');
    }

    public function lines()
    {
        return $this->hasMany(BusinessDocumentLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function signatories()
    {
        return $this->hasMany(BusinessDocumentSignatory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(BusinessDocumentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function payments()
    {
        return $this->hasMany(BusinessDocumentPayment::class)->orderByDesc('payment_date')->orderByDesc('id');
    }

    public function securityDeposit()
    {
        return $this->hasOne(SecurityDeposit::class, 'context_id')->where('context_type', 'event_document');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function getIsLockedAttribute(): bool
    {
        return $this->status !== 'draft';
    }
}
