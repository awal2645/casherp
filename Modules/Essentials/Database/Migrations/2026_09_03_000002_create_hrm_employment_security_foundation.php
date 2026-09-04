<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hrm_employment_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->string('employee_number', 80);
            $table->string('employment_status', 40)->default('active');
            $table->string('employment_type', 40)->nullable();
            $table->string('preferred_name')->nullable();
            $table->string('work_email')->nullable();
            $table->string('work_phone', 60)->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('designation_id')->nullable();
            $table->unsignedBigInteger('manager_profile_id')->nullable();
            $table->string('job_title')->nullable();
            $table->string('grade', 80)->nullable();
            $table->string('cost_center', 100)->nullable();
            $table->string('project_code', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('compensation')->nullable()->comment('Application-encrypted salary and pay-cycle data.');
            $table->text('bank_details')->nullable()->comment('Application-encrypted payment destination data.');
            $table->text('tax_identifiers')->nullable()->comment('Application-encrypted tax and statutory identifiers.');
            $table->text('medical_data')->nullable()->comment('Application-encrypted restricted medical data.');
            $table->text('diversity_data')->nullable()->comment('Application-encrypted optional restricted diversity data.');
            $table->text('disciplinary_data')->nullable()->comment('Application-encrypted restricted employee-relations data.');
            $table->text('personal_data')->nullable()->comment('Application-encrypted emergency contact and private personal data.');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'user_id'], 'hrm_profile_business_user_unique');
            $table->unique(['business_id', 'employee_number'], 'hrm_profile_business_number_unique');
            $table->index(['business_id', 'employment_status'], 'hrm_profile_business_status_index');
            $table->index(['business_id', 'manager_profile_id'], 'hrm_profile_business_manager_index');
            $table->index(['business_id', 'department_id'], 'hrm_profile_business_department_index');
        });

        Schema::create('hrm_employment_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedInteger('business_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('designation_id')->nullable();
            $table->unsignedBigInteger('manager_profile_id')->nullable();
            $table->string('job_title')->nullable();
            $table->string('grade', 80)->nullable();
            $table->string('cost_center', 100)->nullable();
            $table->string('project_code', 100)->nullable();
            $table->string('employment_type', 40)->nullable();
            $table->string('change_reason')->nullable();
            $table->string('source', 40)->default('hr_update');
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'effective_from', 'effective_to'], 'hrm_assignment_business_dates_index');
            $table->index(['employment_profile_id', 'effective_from'], 'hrm_assignment_profile_date_index');
        });

        Schema::create('hrm_audit_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('event_type', 100);
            $table->string('subject_type', 191);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique('correlation_id', 'hrm_audit_correlation_unique');
            $table->index(['business_id', 'subject_type', 'subject_id'], 'hrm_audit_subject_index');
            $table->index(['business_id', 'event_type', 'occurred_at'], 'hrm_audit_event_time_index');
        });

        Schema::create('hrm_sensitive_access_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('actor_user_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('field_group', 60);
            $table->string('action', 30)->default('view');
            $table->string('purpose', 191);
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->unique('correlation_id', 'hrm_sensitive_access_correlation_unique');
            $table->index(['business_id', 'employment_profile_id', 'accessed_at'], 'hrm_sensitive_profile_time_index');
            $table->index(['business_id', 'actor_user_id', 'accessed_at'], 'hrm_sensitive_actor_time_index');
        });

        $this->backfillProfiles();
    }

    private function backfillProfiles(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $now = now();
        DB::table('users')
            ->whereNotNull('business_id')
            ->where('user_type', 'user')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($now) {
                foreach ($users as $user) {
                    $businessId = (int) $user->business_id;
                    $userId = (int) $user->id;
                    if ($businessId <= 0 || $userId <= 0) {
                        continue;
                    }

                    $compensation = [
                        'amount' => isset($user->essentials_salary) ? (float) $user->essentials_salary : null,
                        'basis' => $user->essentials_pay_period ?? null,
                        'pay_period' => $user->essentials_pay_period ?? null,
                        'pay_cycle' => $user->essentials_pay_cycle ?? null,
                    ];
                    $bankDetails = ! empty($user->bank_details)
                        ? json_decode($user->bank_details, true)
                        : null;

                    $profileId = DB::table('hrm_employment_profiles')->insertGetId([
                        'business_id' => $businessId,
                        'user_id' => $userId,
                        'employee_number' => 'EMP-'.str_pad((string) $userId, 6, '0', STR_PAD_LEFT),
                        'employment_status' => ($user->status ?? null) === 'inactive' ? 'inactive' : 'active',
                        'location_id' => $user->location_id ?? null,
                        'department_id' => $user->essentials_department_id ?? null,
                        'designation_id' => $user->essentials_designation_id ?? null,
                        'compensation' => $this->encryptArray($compensation),
                        'bank_details' => $this->encryptArray($bankDetails),
                        'metadata' => json_encode(['legacy_user_backfill' => true]),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('hrm_employment_assignments')->insert([
                        'employment_profile_id' => $profileId,
                        'business_id' => $businessId,
                        'effective_from' => $user->created_at
                            ? substr((string) $user->created_at, 0, 10)
                            : $now->toDateString(),
                        'location_id' => $user->location_id ?? null,
                        'department_id' => $user->essentials_department_id ?? null,
                        'designation_id' => $user->essentials_designation_id ?? null,
                        'employment_type' => 'employee',
                        'change_reason' => 'Initial migration from legacy employee fields',
                        'source' => 'migration',
                        'changed_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    private function encryptArray(?array $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Crypt::encryptString(json_encode($value));
    }

    public function down()
    {
        Schema::dropIfExists('hrm_sensitive_access_logs');
        Schema::dropIfExists('hrm_audit_events');
        Schema::dropIfExists('hrm_employment_assignments');
        Schema::dropIfExists('hrm_employment_profiles');
    }
};
