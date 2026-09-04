<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class PayrollApproval extends Model
{
    protected $table = 'hrm_payroll_approvals';
    protected $guarded = ['id'];
    protected $casts = ['decided_at' => 'datetime'];

    public function run() { return $this->belongsTo(PayrollRun::class, 'payroll_run_id'); }
    public function actor() { return $this->belongsTo(\App\User::class, 'actor_user_id'); }
}
