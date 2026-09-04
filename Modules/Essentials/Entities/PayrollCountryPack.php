<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollCountryPack extends Model
{
    protected $table = 'hrm_payroll_country_packs';
    protected $guarded = ['id'];
    protected $casts = ['rules' => 'array', 'effective_from' => 'date', 'effective_to' => 'date', 'professionally_reviewed' => 'boolean', 'reviewed_at' => 'datetime', 'is_active' => 'boolean'];

    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
