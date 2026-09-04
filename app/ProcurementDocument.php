<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProcurementDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'budget_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function parentTransaction()
    {
        return $this->belongsTo(Transaction::class, 'parent_transaction_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function department()
    {
        return $this->belongsTo(Category::class, 'department_id');
    }

    public function approvals()
    {
        return $this->hasMany(ProcurementApprovalStep::class)->orderBy('sequence');
    }

    public function events()
    {
        return $this->hasMany(ProcurementEvent::class)->latest();
    }

    public function quotes()
    {
        return $this->hasMany(ProcurementSupplierQuote::class, 'requisition_transaction_id', 'transaction_id');
    }

    public function selectedQuote()
    {
        return $this->belongsTo(ProcurementSupplierQuote::class, 'selected_quote_id');
    }

    public function autoExpense()
    {
        return $this->belongsTo(Transaction::class, 'auto_expense_transaction_id');
    }
}
