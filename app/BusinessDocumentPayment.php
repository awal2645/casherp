<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BusinessDocumentPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'reversed_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(BusinessDocument::class, 'business_document_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
