<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollRun extends Model
{
    use SoftDeletes;

    protected $table = 'hrm_payroll_runs';
    protected $guarded = ['id'];
    protected $casts = ['variance_summary' => 'array', 'calculated_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'paid_at' => 'datetime', 'closed_at' => 'datetime'];

    public function period() { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
    public function countryPack() { return $this->belongsTo(PayrollCountryPack::class, 'country_pack_id'); }
    public function items() { return $this->hasMany(PayrollRunItem::class); }
    public function approvals() { return $this->hasMany(PayrollApproval::class); }
    public function inputs() { return $this->hasMany(PayrollInput::class); }
    public function paymentBatches() { return $this->hasMany(PayrollPaymentBatch::class); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
