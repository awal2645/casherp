<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    protected $table = 'hrm_employee_documents';
    protected $guarded = ['id'];
    protected $casts = ['issued_on' => 'date', 'expires_on' => 'date', 'retention_until' => 'date', 'legal_hold' => 'boolean'];

    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
