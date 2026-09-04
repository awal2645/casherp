<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class EmploymentAssignment extends Model
{
    protected $table = 'hrm_employment_assignments';

    protected $guarded = ['id'];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function employmentProfile()
    {
        return $this->belongsTo(EmploymentProfile::class);
    }
}
