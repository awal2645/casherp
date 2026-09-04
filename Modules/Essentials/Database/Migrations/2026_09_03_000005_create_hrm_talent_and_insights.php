<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hrm_job_requisitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('reference', 80);
            $table->string('title');
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedBigInteger('hiring_manager_profile_id')->nullable();
            $table->unsignedInteger('headcount')->default(1);
            $table->string('employment_type', 40)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('salary_min', 22, 4)->nullable();
            $table->decimal('salary_max', 22, 4)->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->date('target_start_date')->nullable();
            $table->date('closed_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'hrm_job_requisition_uuid_unique');
            $table->unique(['business_id', 'reference'], 'hrm_job_requisition_reference_unique');
            $table->index(['business_id', 'status', 'created_at'], 'hrm_job_requisition_status_index');
        });

        Schema::create('hrm_candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('source', 80)->nullable();
            $table->string('resume_path')->nullable();
            $table->json('consents')->nullable();
            $table->date('retention_until')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'hrm_candidate_uuid_unique');
            $table->index(['business_id', 'email'], 'hrm_candidate_email_index');
        });

        Schema::create('hrm_job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('job_requisition_id');
            $table->unsignedBigInteger('candidate_id');
            $table->string('stage', 40)->default('applied');
            $table->string('status', 30)->default('active');
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('decision_reason')->nullable();
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('last_stage_at')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_job_application_uuid_unique');
            $table->unique(['job_requisition_id', 'candidate_id'], 'hrm_job_application_candidate_unique');
            $table->index(['business_id', 'stage', 'status'], 'hrm_job_application_pipeline_index');
        });

        Schema::create('hrm_interviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('job_application_id');
            $table->string('interview_type', 40);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone');
            $table->string('location_or_link')->nullable();
            $table->json('interviewer_user_ids');
            $table->string('status', 30)->default('scheduled');
            $table->json('scorecard')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_interview_uuid_unique');
            $table->index(['business_id', 'starts_at', 'status'], 'hrm_interview_schedule_index');
        });

        Schema::create('hrm_performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('review_due_on')->nullable();
            $table->string('status', 30)->default('draft');
            $table->json('rating_scale')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_performance_cycle_uuid_unique');
            $table->index(['business_id', 'status', 'starts_on'], 'hrm_performance_cycle_status_index');
        });

        Schema::create('hrm_performance_goals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('performance_cycle_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 7, 2)->default(100);
            $table->string('measure_type', 30)->default('percentage');
            $table->decimal('target_value', 22, 4)->nullable();
            $table->decimal('current_value', 22, 4)->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_performance_goal_uuid_unique');
            $table->index(['business_id', 'employment_profile_id', 'status'], 'hrm_performance_goal_profile_index');
        });

        Schema::create('hrm_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('performance_cycle_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedBigInteger('reviewer_profile_id');
            $table->string('review_type', 30)->default('manager');
            $table->string('status', 30)->default('draft');
            $table->decimal('overall_rating', 6, 2)->nullable();
            $table->json('ratings')->nullable();
            $table->text('strengths')->nullable();
            $table->text('development_areas')->nullable();
            $table->text('employee_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_performance_review_uuid_unique');
            $table->unique(['performance_cycle_id', 'employment_profile_id', 'reviewer_profile_id', 'review_type'], 'hrm_performance_review_unique');
            $table->index(['business_id', 'status', 'submitted_at'], 'hrm_performance_review_status_index');
        });

        Schema::create('hrm_learning_courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('delivery_method', 40)->default('classroom');
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->unsignedInteger('validity_months')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_learning_course_uuid_unique');
            $table->unique(['business_id', 'code'], 'hrm_learning_course_code_unique');
        });

        Schema::create('hrm_learning_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('learning_course_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('status', 30)->default('assigned');
            $table->date('assigned_on');
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('certificate_expires_on')->nullable();
            $table->unsignedInteger('assigned_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_learning_enrollment_uuid_unique');
            $table->unique(['learning_course_id', 'employment_profile_id', 'assigned_on'], 'hrm_learning_enrollment_unique');
            $table->index(['business_id', 'status', 'due_on'], 'hrm_learning_enrollment_due_index');
        });

        Schema::create('hrm_benefit_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('code', 80);
            $table->string('name');
            $table->string('benefit_type', 60);
            $table->string('provider')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('employer_amount', 22, 4)->default(0);
            $table->decimal('employee_amount', 22, 4)->default(0);
            $table->json('eligibility_rules')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_benefit_plan_uuid_unique');
            $table->unique(['business_id', 'code'], 'hrm_benefit_plan_code_unique');
        });

        Schema::create('hrm_benefit_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('benefit_plan_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('active');
            $table->json('dependants')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_benefit_enrollment_uuid_unique');
            $table->index(['business_id', 'employment_profile_id', 'status'], 'hrm_benefit_enrollment_profile_index');
        });

        Schema::create('hrm_employee_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id')->nullable();
            $table->string('case_number', 80);
            $table->string('case_type', 50);
            $table->string('severity', 30)->default('normal');
            $table->string('title');
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->date('reported_on');
            $table->date('target_resolution_on')->nullable();
            $table->date('resolved_on')->nullable();
            $table->json('resolution')->nullable();
            $table->string('access_level', 30)->default('restricted');
            $table->unsignedInteger('owner_user_id')->nullable();
            $table->unsignedInteger('reported_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'hrm_employee_case_uuid_unique');
            $table->unique(['business_id', 'case_number'], 'hrm_employee_case_number_unique');
            $table->index(['business_id', 'case_type', 'status'], 'hrm_employee_case_status_index');
        });

        Schema::create('hrm_succession_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('critical_role');
            $table->unsignedInteger('department_id')->nullable();
            $table->unsignedBigInteger('incumbent_profile_id')->nullable();
            $table->unsignedBigInteger('successor_profile_id');
            $table->string('readiness', 40);
            $table->decimal('risk_score', 6, 2)->nullable();
            $table->text('development_plan')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_succession_plan_uuid_unique');
            $table->index(['business_id', 'status', 'readiness'], 'hrm_succession_plan_status_index');
        });

        Schema::create('hrm_engagement_surveys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->json('questions');
            $table->boolean('is_anonymous')->default(true);
            $table->unsignedInteger('minimum_group_size')->default(5);
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_engagement_survey_uuid_unique');
            $table->index(['business_id', 'status', 'opens_on'], 'hrm_engagement_survey_status_index');
        });

        Schema::create('hrm_engagement_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('engagement_survey_id');
            $table->unsignedBigInteger('employment_profile_id')->nullable();
            $table->string('anonymous_token_hash', 64)->nullable();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique('uuid', 'hrm_engagement_response_uuid_unique');
            $table->unique(['engagement_survey_id', 'employment_profile_id'], 'hrm_engagement_response_profile_unique');
            $table->unique(['engagement_survey_id', 'anonymous_token_hash'], 'hrm_engagement_response_anonymous_unique');
            $table->index(['business_id', 'engagement_survey_id'], 'hrm_engagement_response_survey_index');
        });

        Schema::create('hrm_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->date('snapshot_date');
            $table->string('metric_key', 80);
            $table->string('dimension_type', 40)->default('company');
            $table->string('dimension_value')->default('all');
            $table->decimal('metric_value', 22, 6);
            $table->unsignedInteger('population')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'snapshot_date', 'metric_key', 'dimension_type', 'dimension_value'], 'hrm_metric_snapshot_unique');
            $table->index(['business_id', 'metric_key', 'snapshot_date'], 'hrm_metric_snapshot_key_date_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hrm_metric_snapshots');
        Schema::dropIfExists('hrm_engagement_responses');
        Schema::dropIfExists('hrm_engagement_surveys');
        Schema::dropIfExists('hrm_succession_plans');
        Schema::dropIfExists('hrm_employee_cases');
        Schema::dropIfExists('hrm_benefit_enrollments');
        Schema::dropIfExists('hrm_benefit_plans');
        Schema::dropIfExists('hrm_learning_enrollments');
        Schema::dropIfExists('hrm_learning_courses');
        Schema::dropIfExists('hrm_performance_reviews');
        Schema::dropIfExists('hrm_performance_goals');
        Schema::dropIfExists('hrm_performance_cycles');
        Schema::dropIfExists('hrm_interviews');
        Schema::dropIfExists('hrm_job_applications');
        Schema::dropIfExists('hrm_candidates');
        Schema::dropIfExists('hrm_job_requisitions');
    }
};
