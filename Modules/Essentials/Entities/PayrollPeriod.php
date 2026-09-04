<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $table = 'hrm_payroll_periods';
    protected $guarded = ['id'];
    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'payment_date' => 'date', 'locked_at' => 'datetime'];

    public function runs() { return $this->hasMany(PayrollRun::class); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
