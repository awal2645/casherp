<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SecurityDepositEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:4',
        'occurred_on' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function deposit()
    {
        return $this->belongsTo(SecurityDeposit::class, 'security_deposit_id');
    }

    public function document()
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }
}
