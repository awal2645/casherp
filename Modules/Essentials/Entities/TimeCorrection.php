<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class TimeCorrection extends Model
{
    protected $table = 'hrm_time_corrections';
    protected $guarded = ['id'];
    protected $casts = ['requested_clock_in' => 'datetime', 'requested_clock_out' => 'datetime', 'decided_at' => 'datetime'];

    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
