<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class LeaveAccount extends Model
{
    protected $table = 'hrm_leave_accounts';
    protected $guarded = ['id'];
    protected $casts = ['opening_balance' => 'decimal:3', 'accrued' => 'decimal:3', 'carried_forward' => 'decimal:3', 'adjusted' => 'decimal:3', 'reserved' => 'decimal:3', 'used' => 'decimal:3', 'expired' => 'decimal:3'];

    public function profile() { return $this->belongsTo(EmploymentProfile::class, 'employment_profile_id'); }
    public function leaveType() { return $this->belongsTo(EssentialsLeaveType::class, 'leave_type_id'); }
    public function ledgerEntries() { return $this->hasMany(LeaveLedgerEntry::class); }
    public function scopeForBusiness($query, int $businessId) { return $query->where($this->qualifyColumn('business_id'), $businessId); }
    public function availableBalance(): float { return round((float) $this->opening_balance + (float) $this->accrued + (float) $this->carried_forward + (float) $this->adjusted - (float) $this->reserved - (float) $this->used - (float) $this->expired, 3); }
}
