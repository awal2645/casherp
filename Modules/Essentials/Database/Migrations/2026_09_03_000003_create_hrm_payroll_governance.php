<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hrm_payroll_country_packs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->char('country_code', 2);
            $table->string('jurisdiction')->default('');
            $table->string('name');
            $table->string('version', 40);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('rules');
            $table->boolean('professionally_reviewed')->default(false);
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->unique(['business_id', 'country_code', 'jurisdiction', 'version'], 'hrm_country_pack_version_unique');
            $table->index(['business_id', 'is_active', 'effective_from'], 'hrm_country_pack_active_index');
        });

        Schema::create('hrm_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('code', 80);
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('payment_date')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('locked_at')->nullable();
            $table->unsignedInteger('locked_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'code'], 'hrm_payroll_period_code_unique');
            $table->index(['business_id', 'starts_on', 'ends_on'], 'hrm_payroll_period_dates_index');
        });

        Schema::create('hrm_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_period_id');
            $table->unsignedBigInteger('country_pack_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('name');
            $table->string('run_type', 30)->default('regular');
            $table->string('status', 30)->default('draft');
            $table->char('currency_code', 3);
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->string('formula_version', 80)->default('core-1');
            $table->decimal('gross_total', 22, 4)->default(0);
            $table->decimal('deduction_total', 22, 4)->default(0);
            $table->decimal('net_total', 22, 4)->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->json('variance_summary')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'hrm_payroll_run_uuid_unique');
            $table->index(['business_id', 'payroll_period_id', 'status'], 'hrm_payroll_run_period_status_index');
        });

        Schema::create('hrm_payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->unsignedInteger('user_id');
            $table->string('employee_number', 80);
            $table->json('employment_snapshot');
            $table->json('calculation_inputs');
            $table->json('earnings');
            $table->json('deductions');
            $table->json('statutory_results')->nullable();
            $table->decimal('gross_amount', 22, 4);
            $table->decimal('deduction_amount', 22, 4);
            $table->decimal('net_amount', 22, 4);
            $table->decimal('prior_period_net', 22, 4)->nullable();
            $table->decimal('variance_percent', 9, 4)->nullable();
            $table->json('alerts')->nullable();
            $table->string('status', 30)->default('calculated');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employment_profile_id'], 'hrm_payroll_run_employee_unique');
            $table->index(['business_id', 'user_id', 'status'], 'hrm_payroll_item_user_status_index');
        });

        Schema::create('hrm_payroll_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('stage', 30);
            $table->string('decision', 30);
            $table->unsignedInteger('actor_user_id');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique('uuid', 'hrm_payroll_approval_uuid_unique');
            $table->index(['business_id', 'payroll_run_id', 'stage'], 'hrm_payroll_approval_run_stage_index');
        });

        Schema::create('hrm_payroll_inputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employment_profile_id');
            $table->string('input_type', 30);
            $table->string('code', 40);
            $table->string('label', 100);
            $table->decimal('quantity', 16, 4)->default(1);
            $table->decimal('rate', 22, 4)->nullable();
            $table->decimal('amount', 22, 4);
            $table->string('source', 40)->default('manual');
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_payroll_input_uuid_unique');
            $table->index(['business_id', 'payroll_run_id', 'status'], 'hrm_payroll_input_run_status_index');
            $table->index(['employment_profile_id', 'code'], 'hrm_payroll_input_profile_code_index');
        });

        Schema::create('hrm_payroll_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('reference', 100);
            $table->string('payment_method', 40);
            $table->unsignedInteger('account_id')->nullable();
            $table->char('currency_code', 3);
            $table->decimal('amount', 22, 4);
            $table->string('status', 30)->default('prepared');
            $table->string('provider_reference')->nullable();
            $table->string('bank_file_path')->nullable();
            $table->json('reconciliation')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'hrm_payroll_batch_uuid_unique');
            $table->unique(['business_id', 'reference'], 'hrm_payroll_batch_reference_unique');
        });

        Schema::create('hrm_payroll_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('payroll_payment_batch_id');
            $table->unsignedBigInteger('payroll_run_item_id');
            $table->decimal('amount', 22, 4);
            $table->string('status', 30)->default('prepared');
            $table->string('provider_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['payroll_payment_batch_id', 'payroll_run_item_id'], 'hrm_payroll_batch_item_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hrm_payroll_payment_allocations');
        Schema::dropIfExists('hrm_payroll_payment_batches');
        Schema::dropIfExists('hrm_payroll_inputs');
        Schema::dropIfExists('hrm_payroll_approvals');
        Schema::dropIfExists('hrm_payroll_run_items');
        Schema::dropIfExists('hrm_payroll_runs');
        Schema::dropIfExists('hrm_payroll_periods');
        Schema::dropIfExists('hrm_payroll_country_packs');
    }
};
