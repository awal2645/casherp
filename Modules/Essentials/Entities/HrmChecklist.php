<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmChecklist extends Model
{
    protected $table = 'hrm_checklists';
    protected $guarded = ['id'];
    protected $casts = ['due_date' => 'date'];

    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function tasks() { return $this->hasMany(HrmChecklistTask::class, 'checklist_id')->orderBy('sort_order'); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
