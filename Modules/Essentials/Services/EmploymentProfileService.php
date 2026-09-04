<?php

namespace Modules\Essentials\Services;

use App\Business;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EmploymentAssignment;
use Modules\Essentials\Entities\EmploymentProfile;

class EmploymentProfileService
{
    private HrmAuditService $audit;

    public function __construct(HrmAuditService $audit)
    {
        $this->audit = $audit;
    }

    public function find(int $businessId, int $userId): ?EmploymentProfile
    {
        return EmploymentProfile::forBusiness($businessId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findOrFail(int $businessId, int $userId): EmploymentProfile
    {
        return EmploymentProfile::forBusiness($businessId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    public function ensure(int $businessId, User $user, ?int $actorId = null): EmploymentProfile
    {
        Business::whereKey($businessId)->firstOrFail();

        $profile = EmploymentProfile::firstOrCreate(
            ['business_id' => $businessId, 'user_id' => $user->id],
            [
                'employee_number' => $this->nextEmployeeNumber($businessId, $user->id),
                'employment_status' => $user->status === 'inactive' ? 'inactive' : 'active',
                'preferred_name' => $user->first_name,
                'work_email' => $user->email,
                'work_phone' => $user->contact_number,
                'location_id' => $user->location_id,
                'department_id' => $user->essentials_department_id,
                'designation_id' => $user->essentials_designation_id,
                'compensation' => [
                    'amount' => $user->essentials_salary !== null ? (float) $user->essentials_salary : null,
                    'basis' => $user->essentials_pay_period,
                    'pay_period' => $user->essentials_pay_period,
                    'pay_cycle' => $user->essentials_pay_cycle,
                ],
                // Legacy sensitive data is backfilled by the migration. New
                // writes must pass the field-level permission checks and be
                // supplied explicitly to syncFromUserForm().
                'bank_details' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]
        );

        if ($profile->wasRecentlyCreated) {
            EmploymentAssignment::create([
                'employment_profile_id' => $profile->id,
                'business_id' => $businessId,
                'effective_from' => optional($user->created_at)->toDateString() ?: now()->toDateString(),
                'location_id' => $profile->location_id,
                'department_id' => $profile->department_id,
                'designation_id' => $profile->designation_id,
                'manager_profile_id' => $profile->manager_profile_id,
                'job_title' => $profile->job_title,
                'grade' => $profile->grade,
                'cost_center' => $profile->cost_center,
                'project_code' => $profile->project_code,
                'employment_type' => $profile->employment_type,
                'change_reason' => 'Initial employment profile created',
                'source' => 'profile_creation',
                'changed_by' => $actorId,
            ]);
            $this->audit->record($businessId, 'employment_profile.created', EmploymentProfile::class, $profile->id, [], $profile->toArray(), 'Initial employment profile created');
        }

        return $profile;
    }

    public function syncFromUserForm(int $businessId, User $user, array $input, int $actorId): EmploymentProfile
    {
        return DB::transaction(function () use ($businessId, $user, $input, $actorId) {
            $profile = $this->ensure($businessId, $user, $actorId);
            $profile = EmploymentProfile::forBusiness($businessId)->lockForUpdate()->findOrFail($profile->id);
            $before = $profile->only([
                'employee_number', 'employment_status', 'employment_type', 'location_id',
                'department_id', 'designation_id', 'manager_profile_id', 'job_title',
                'grade', 'cost_center', 'project_code', 'hire_date', 'probation_end_date',
                'confirmation_date', 'termination_date',
            ]);

            $employment = Arr::only($input, [
                'employee_number', 'employment_status', 'employment_type', 'preferred_name',
                'work_email', 'work_phone', 'location_id', 'department_id', 'designation_id',
                'manager_profile_id', 'job_title', 'grade', 'cost_center', 'project_code',
                'hire_date', 'probation_end_date', 'confirmation_date', 'termination_date', 'metadata',
            ]);
            $employment['updated_by'] = $actorId;
            $employment['version'] = $profile->version + 1;

            if (array_key_exists('compensation', $input)) {
                $employment['compensation'] = $input['compensation'];
            }
            foreach (['bank_details', 'tax_identifiers', 'medical_data', 'diversity_data', 'disciplinary_data', 'personal_data'] as $field) {
                if (array_key_exists($field, $input)) {
                    $employment[$field] = $input[$field];
                }
            }

            $profile->fill($employment);
            $profile->save();

            $this->recordAssignmentIfChanged($profile, $before, $input, $actorId);
            $this->audit->record(
                $businessId,
                'employment_profile.updated',
                EmploymentProfile::class,
                $profile->id,
                $before,
                $profile->only(array_keys($before)),
                $input['change_reason'] ?? null
            );

            return $profile->fresh();
        });
    }

    public function applyToLegacyView(User $user, EmploymentProfile $profile, bool $includeSensitive = false): User
    {
        $compensation = (array) $profile->compensation;
        $user->setAttribute('essentials_department_id', $profile->department_id);
        $user->setAttribute('essentials_designation_id', $profile->designation_id);
        $user->setAttribute('essentials_salary', $compensation['amount'] ?? null);
        $user->setAttribute('essentials_pay_period', $compensation['pay_period'] ?? ($compensation['basis'] ?? null));
        $user->setAttribute('essentials_pay_cycle', $compensation['pay_cycle'] ?? null);
        $user->setAttribute('location_id', $profile->location_id);
        $user->setAttribute(
            'bank_details',
            json_encode($includeSensitive ? (array) $profile->bank_details : $profile->maskedBankDetails())
        );
        $user->setRelation('employmentProfile', $profile);

        return $user;
    }

    private function recordAssignmentIfChanged(EmploymentProfile $profile, array $before, array $input, int $actorId): void
    {
        $assignmentFields = [
            'location_id', 'department_id', 'designation_id', 'manager_profile_id',
            'job_title', 'grade', 'cost_center', 'project_code', 'employment_type',
        ];
        $changed = false;
        foreach ($assignmentFields as $field) {
            if (($before[$field] ?? null) != ($profile->{$field} ?? null)) {
                $changed = true;
                break;
            }
        }
        if (! $changed) {
            return;
        }

        $effectiveFrom = $input['effective_from'] ?? now()->toDateString();
        $existing = EmploymentAssignment::where('business_id', $profile->business_id)
            ->where('employment_profile_id', $profile->id)
            ->whereNull('effective_to')
            ->lockForUpdate()
            ->latest('effective_from')
            ->first();
        if ($existing) {
            if ($existing->effective_from->toDateString() > $effectiveFrom) {
                throw ValidationException::withMessages([
                    'effective_from' => 'The effective date cannot precede the current assignment start date.',
                ]);
            }
            if ($existing->effective_from->toDateString() === $effectiveFrom) {
                $existing->fill(array_merge(
                    Arr::only($profile->toArray(), $assignmentFields),
                    [
                        'change_reason' => $input['change_reason'] ?? 'Employment details updated',
                        'source' => $input['source'] ?? 'hr_update',
                        'changed_by' => $actorId,
                    ]
                ));
                $existing->save();

                return;
            }
            if ($existing->effective_from->toDateString() < $effectiveFrom) {
                $existing->effective_to = \Carbon\Carbon::parse($effectiveFrom)->subDay()->toDateString();
                $existing->save();
            }
        }

        EmploymentAssignment::create(array_merge(
            Arr::only($profile->toArray(), $assignmentFields),
            [
                'employment_profile_id' => $profile->id,
                'business_id' => $profile->business_id,
                'effective_from' => $effectiveFrom,
                'change_reason' => $input['change_reason'] ?? 'Employment details updated',
                'source' => $input['source'] ?? 'hr_update',
                'changed_by' => $actorId,
            ]
        ));
    }

    private function nextEmployeeNumber(int $businessId, int $userId): string
    {
        $base = 'EMP-'.str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
        if (! EmploymentProfile::forBusiness($businessId)->where('employee_number', $base)->exists()) {
            return $base;
        }

        return $base.'-'.substr((string) \Illuminate\Support\Str::uuid(), 0, 6);
    }
}
