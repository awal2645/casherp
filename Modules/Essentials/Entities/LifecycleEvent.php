<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class LifecycleEvent extends Model
{
    protected $table = 'hrm_lifecycle_events';
    protected $guarded = ['id'];
    protected $casts = ['effective_date' => 'date', 'details' => 'array'];

    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function checklists() { return $this->hasMany(HrmChecklist::class, 'lifecycle_event_id'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
