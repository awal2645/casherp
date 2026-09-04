<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class WorkCalendar extends Model
{
    protected $table = 'hrm_work_calendars';
    protected $guarded = ['id'];
    protected $casts = ['weekly_schedule' => 'array', 'weekend_days' => 'array', 'is_default' => 'boolean', 'is_active' => 'boolean'];

    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
}
