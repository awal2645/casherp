<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollPaymentBatch extends Model
{
    protected $table = 'hrm_payroll_payment_batches';
    protected $guarded = ['id'];
    protected $casts = ['reconciliation' => 'array', 'submitted_at' => 'datetime', 'reconciled_at' => 'datetime'];

    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function allocations() { return $this->hasMany(PayrollPaymentAllocation::class, 'payroll_payment_batch_id'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
