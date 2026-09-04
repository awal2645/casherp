<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hrm_work_calendars', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->string('name');
            $table->string('timezone');
            $table->json('weekly_schedule');
            $table->json('weekend_days')->nullable();
            $table->decimal('standard_hours_per_week', 8, 2)->default(40);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['business_id', 'name'], 'hrm_work_calendar_name_unique');
            $table->index(['business_id', 'location_id', 'is_active'], 'hrm_work_calendar_scope_index');
        });

        Schema::create('hrm_leave_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedInteger('leave_type_id');
            $table->string('policy_period', 40);
            $table->decimal('opening_balance', 10, 3)->default(0);
            $table->decimal('accrued', 10, 3)->default(0);
            $table->decimal('carried_forward', 10, 3)->default(0);
            $table->decimal('adjusted', 10, 3)->default(0);
            $table->decimal('reserved', 10, 3)->default(0);
            $table->decimal('used', 10, 3)->default(0);
            $table->decimal('expired', 10, 3)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['employment_profile_id', 'leave_type_id', 'policy_period'], 'hrm_leave_account_unique');
            $table->index(['business_id', 'policy_period'], 'hrm_leave_account_period_index');
        });

        Schema::create('hrm_leave_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('leave_account_id');
            $table->unsignedInteger('leave_id')->nullable();
            $table->string('entry_type', 30);
            $table->decimal('quantity', 10, 3);
            $table->decimal('balance_after', 10, 3);
            $table->date('effective_date');
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_leave_ledger_uuid_unique');
            $table->index(['business_id', 'leave_account_id', 'effective_date'], 'hrm_leave_ledger_account_date_index');
        });

        Schema::create('hrm_time_corrections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedInteger('attendance_id')->nullable();
            $table->string('correction_type', 40);
            $table->dateTime('requested_clock_in')->nullable();
            $table->dateTime('requested_clock_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->text('reason');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_time_correction_uuid_unique');
            $table->index(['business_id', 'status', 'created_at'], 'hrm_time_correction_status_index');
        });

        Schema::create('hrm_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('document_type', 80);
            $table->string('name');
            $table->string('version', 40)->default('1');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('access_level', 40)->default('employee_hr');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->date('retention_until')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'hrm_employee_document_uuid_unique');
            $table->index(['business_id', 'employment_profile_id', 'status'], 'hrm_employee_document_profile_index');
            $table->index(['business_id', 'expires_on'], 'hrm_employee_document_expiry_index');
        });

        Schema::create('hrm_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('event_type', 40);
            $table->date('effective_date');
            $table->string('status', 30)->default('planned');
            $table->json('details')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique('uuid', 'hrm_lifecycle_event_uuid_unique');
            $table->index(['business_id', 'employment_profile_id', 'effective_date'], 'hrm_lifecycle_profile_date_index');
        });

        Schema::create('hrm_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedBigInteger('lifecycle_event_id')->nullable();
            $table->string('checklist_type', 40);
            $table->string('name');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('open');
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->index(['business_id', 'status', 'due_date'], 'hrm_checklist_status_due_index');
        });

        Schema::create('hrm_checklist_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('checklist_id');
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['business_id', 'assigned_to', 'status'], 'hrm_checklist_task_assignee_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hrm_checklist_tasks');
        Schema::dropIfExists('hrm_checklists');
        Schema::dropIfExists('hrm_lifecycle_events');
        Schema::dropIfExists('hrm_employee_documents');
        Schema::dropIfExists('hrm_time_corrections');
        Schema::dropIfExists('hrm_leave_ledger_entries');
        Schema::dropIfExists('hrm_leave_accounts');
        Schema::dropIfExists('hrm_work_calendars');
    }
};
