<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentProfile extends Model
{
    use SoftDeletes;

    protected $table = 'hrm_employment_profiles';

    protected $guarded = ['id'];

    protected $hidden = [
        'compensation',
        'bank_details',
        'tax_identifiers',
        'medical_data',
        'diversity_data',
        'disciplinary_data',
        'personal_data',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'confirmation_date' => 'date',
        'termination_date' => 'date',
        'compensation' => 'encrypted:array',
        'bank_details' => 'encrypted:array',
        'tax_identifiers' => 'encrypted:array',
        'medical_data' => 'encrypted:array',
        'diversity_data' => 'encrypted:array',
        'disciplinary_data' => 'encrypted:array',
        'personal_data' => 'encrypted:array',
        'metadata' => 'array',
    ];

    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_profile_id');
    }

    public function directReports()
    {
        return $this->hasMany(self::class, 'manager_profile_id');
    }

    public function assignments()
    {
        return $this->hasMany(EmploymentAssignment::class);
    }

    public function leaveAccounts()
    {
        return $this->hasMany(LeaveAccount::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function lifecycleEvents()
    {
        return $this->hasMany(LifecycleEvent::class);
    }

    public function currentAssignment()
    {
        return $this->hasOne(EmploymentAssignment::class)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->latestOfMany('effective_from');
    }

    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where($this->qualifyColumn('business_id'), $businessId);
    }

    public function compensationAmount(): float
    {
        return round((float) data_get($this->compensation, 'amount', 0), 4);
    }

    public function maskedBankDetails(): array
    {
        $details = (array) $this->bank_details;
        $account = (string) ($details['account_number'] ?? '');
        if ($account !== '') {
            $details['account_number'] = str_repeat('•', max(0, mb_strlen($account) - 4)).mb_substr($account, -4);
        }
        if (! empty($details['tax_payer_id'])) {
            $tax = (string) $details['tax_payer_id'];
            $details['tax_payer_id'] = str_repeat('•', max(0, mb_strlen($tax) - 4)).mb_substr($tax, -4);
        }

        return $details;
    }
}
