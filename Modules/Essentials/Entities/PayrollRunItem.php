<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollRunItem extends Model
{
    protected $table = 'hrm_payroll_run_items';
    protected $guarded = ['id'];
    protected $casts = ['employment_snapshot' => 'array', 'calculation_inputs' => 'array', 'earnings' => 'array', 'deductions' => 'array', 'statutory_results' => 'array', 'alerts' => 'array'];

    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function user() { return $this->belongsTo(\App\User::class); }
}
