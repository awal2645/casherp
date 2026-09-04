<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollInput extends Model
{
    protected $table = 'hrm_payroll_inputs';
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'approved_at' => 'datetime'];

    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
