<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use App\User;
use Illuminate\Validation\Rule;
use Modules\Essentials\Entities\EmploymentProfile;

class EmployeeProfileImportHandler extends AbstractDataImportHandler
{
    public function definition(): array
    {
        return [
            'key' => 'employee_profiles',
            'label' => 'HR employee profiles',
            'description' => 'Employment details for existing company users. Login accounts and passwords are never created by import.',
            'permission' => 'essentials.crud_all_attendance',
            'industries' => ['*'],
            'columns' => [
                'email' => ['required' => true, 'example' => 'employee@example.com', 'aliases' => ['login_email', 'user_email']],
                'employee_number' => ['required' => true, 'example' => 'EMP-001', 'aliases' => ['employee_id', 'staff_number']],
                'employment_status' => ['example' => 'active', 'aliases' => ['status']],
                'employment_type' => ['example' => 'full_time', 'aliases' => ['contract_type']],
                'preferred_name' => ['example' => 'Pat'],
                'work_email' => ['example' => 'pat@company.com'],
                'work_phone' => ['example' => '+260 970 000 000'],
                'location_name' => ['example' => 'Head Office', 'aliases' => ['business_location']],
                'job_title' => ['example' => 'Finance Manager', 'aliases' => ['position']],
                'grade' => ['example' => 'M2'],
                'cost_center' => ['example' => 'FIN-01'],
                'project_code' => ['example' => 'ERP-2026'],
                'hire_date' => ['example' => '2026-01-15', 'aliases' => ['start_date']],
            ],
        ];
    }

    protected function rules(Business $business): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191'],
            'employee_number' => ['required', 'string', 'max:80'],
            'employment_status' => ['nullable', Rule::in(['active', 'probation', 'on_leave', 'suspended', 'terminated', 'retired'])],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'fixed_term', 'temporary', 'casual', 'seasonal', 'intern', 'contractor'])],
            'preferred_name' => ['nullable', 'string', 'max:191'],
            'work_email' => ['nullable', 'email:rfc', 'max:191'],
            'work_phone' => ['nullable', 'string', 'max:60'],
            'location_name' => ['nullable', 'string', 'max:191'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'grade' => ['nullable', 'string', 'max:80'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'project_code' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        $data['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;
        $data['work_email'] = ! empty($data['work_email']) ? mb_strtolower($data['work_email']) : null;
        foreach (['employment_status', 'employment_type'] as $key) {
            if (! empty($data[$key])) {
                $data[$key] = strtolower(str_replace([' ', '-'], '_', $data[$key]));
            }
        }
        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        $errors = [];
        $user = ! empty($data['email'])
            ? User::forBusiness($business->id)->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->first()
            : null;
        if (! empty($data['email']) && ! $user) {
            $errors['email'] = 'No existing user with this email belongs to the company. Invite the user first; imports never create passwords.';
        }
        if (! empty($data['location_name']) && ! $this->resolveLocation($business, $location, $data['location_name'])) {
            $errors['location_name'] = 'The company location was not found.';
        }
        if (! empty($data['employee_number'])) {
            $numberOwner = EmploymentProfile::forBusiness($business->id)
                ->where('employee_number', $data['employee_number'])->first();
            if ($numberOwner && (! $user || $numberOwner->user_id !== $user->id)) {
                $errors['employee_number'] = 'The employee number is already assigned to another person in this company.';
            }
        }

        return $errors;
    }

    public function importRow(BusinessDataImport $import, array $data): array
    {
        $user = User::forBusiness($import->business_id)->whereRaw('LOWER(email) = ?', [mb_strtolower($data['email'])])->firstOrFail();
        $location = $this->resolveLocation($import->business, $import->location, $data['location_name'] ?? null);
        $existing = EmploymentProfile::withTrashed()->forBusiness($import->business_id)->where('user_id', $user->id)->first();
        $attributes = $this->withoutNulls([
            'employee_number' => $data['employee_number'],
            'employment_status' => $data['employment_status'] ?? null,
            'employment_type' => $data['employment_type'] ?? null,
            'preferred_name' => $data['preferred_name'] ?? null,
            'work_email' => $data['work_email'] ?? null,
            'work_phone' => $data['work_phone'] ?? null,
            'location_id' => optional($location)->id,
            'job_title' => $data['job_title'] ?? null,
            'grade' => $data['grade'] ?? null,
            'cost_center' => $data['cost_center'] ?? null,
            'project_code' => $data['project_code'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
        ]);
        if ($existing) {
            if ($existing->trashed()) {
                throw new \RuntimeException('This user has an archived employment profile. Restore it before importing updates.');
            }
            $attributes['updated_by'] = $import->approved_by ?: $import->uploaded_by;
            $attributes['version'] = ((int) $existing->version) + 1;
            return $this->handleExisting($import, $existing, $attributes);
        }
        $profile = EmploymentProfile::create($attributes + [
            'business_id' => $import->business_id,
            'user_id' => $user->id,
            'created_by' => $import->approved_by ?: $import->uploaded_by,
            'updated_by' => $import->approved_by ?: $import->uploaded_by,
            'employment_status' => $data['employment_status'] ?? 'active',
            'work_email' => $data['work_email'] ?? $data['email'],
        ]);

        return $this->result($profile, true);
    }

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void
    {
        $this->rollbackModel($import, $row, EmploymentProfile::class);
    }

    protected function assertCanDeleteCreated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->assertNoDependencies($model, [
            ['hrm_employment_assignments', 'employment_profile_id'],
            ['hrm_sensitive_access_logs', 'employment_profile_id'],
            ['hrm_payroll_run_items', 'employment_profile_id'],
            ['hrm_payroll_inputs', 'employment_profile_id'],
            ['hrm_leave_accounts', 'employment_profile_id'],
            ['hrm_time_corrections', 'employment_profile_id'],
            ['hrm_employee_documents', 'employment_profile_id'],
            ['hrm_lifecycle_events', 'employment_profile_id'],
            ['hrm_checklists', 'employment_profile_id'],
            ['hrm_performance_goals', 'employment_profile_id'],
            ['hrm_performance_reviews', 'employment_profile_id'],
            ['hrm_learning_enrollments', 'employment_profile_id'],
            ['hrm_benefit_enrollments', 'employment_profile_id'],
        ]);
    }
}
