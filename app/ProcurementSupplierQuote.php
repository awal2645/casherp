<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProcurementSupplierQuote extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'line_prices' => 'array',
        'delivery_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'shipping_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
    ];

    public function supplier()
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function tax()
    {
        return $this->belongsTo(TaxRate::class, 'tax_id');
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
