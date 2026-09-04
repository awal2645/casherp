<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollPaymentAllocation extends Model
{
    protected $table = 'hrm_payroll_payment_allocations';
    protected $guarded = ['id'];

    public function batch() { return $this->belongsTo(PayrollPaymentBatch::class, 'payroll_payment_batch_id'); }
    public function item() { return $this->belongsTo(PayrollRunItem::class, 'payroll_run_item_id'); }
}
