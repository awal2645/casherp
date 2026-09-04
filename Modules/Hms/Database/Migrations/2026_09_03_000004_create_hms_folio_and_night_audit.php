<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hms_folios', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('hms_group_booking_id')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedBigInteger('hms_guest_profile_id')->nullable();
            $table->string('folio_number', 80);
            $table->string('folio_type', 24)->default('guest');
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->string('status', 24)->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'folio_number'], 'hms_folios_business_number_unique');
            $table->index(['business_id', 'hms_property_id', 'status'], 'hms_folios_property_status_idx');
            $table->index(['business_id', 'transaction_id'], 'hms_folios_booking_idx');
            $table->unique(['business_id', 'hms_group_booking_id'], 'hms_folios_group_unique');
        });

        Schema::create('hms_folio_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_folio_id');
            $table->unsignedBigInteger('linked_entry_id')->nullable();
            $table->unsignedInteger('transaction_payment_id')->nullable();
            $table->string('entry_type', 24);
            $table->string('category', 40);
            $table->string('direction', 8);
            $table->decimal('amount', 22, 4);
            $table->decimal('base_amount', 22, 4);
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->string('payment_method', 40)->nullable();
            $table->date('business_date');
            $table->dateTime('posted_at');
            $table->string('status', 24)->default('posted');
            $table->text('description')->nullable();
            $table->string('idempotency_key', 160)->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'hms_folio_entries_idempotency_unique');
            $table->index(['business_id', 'hms_folio_id', 'status'], 'hms_folio_entries_folio_status_idx');
            $table->index(['business_id', 'business_date'], 'hms_folio_entries_business_date_idx');
        });

        Schema::create('hms_deposit_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_folio_id');
            $table->decimal('amount', 22, 4);
            $table->date('due_date');
            $table->string('status', 24)->default('due');
            $table->unsignedBigInteger('settled_entry_id')->nullable();
            $table->string('purpose', 40)->default('payment_deposit');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'due_date', 'status'], 'hms_deposits_due_idx');
        });

        Schema::create('hms_cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->unsignedInteger('user_id');
            $table->date('business_date');
            $table->decimal('opening_float', 22, 4)->default(0);
            $table->decimal('expected_cash', 22, 4)->default(0);
            $table->decimal('declared_cash', 22, 4)->nullable();
            $table->decimal('variance', 22, 4)->nullable();
            $table->string('status', 16)->default('open');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('variance_reason')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'hms_property_id', 'status'], 'hms_cashier_shifts_property_status_idx');
            $table->index(['business_id', 'user_id', 'business_date'], 'hms_cashier_shifts_user_date_idx');
        });

        Schema::create('hms_night_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->date('business_date');
            $table->string('status', 20)->default('closed');
            $table->json('summary');
            $table->json('exceptions')->nullable();
            $table->boolean('override_used')->default(false);
            $table->text('override_reason')->nullable();
            $table->unsignedInteger('closed_by');
            $table->dateTime('closed_at');
            $table->timestamps();

            $table->unique(['business_id', 'hms_property_id', 'business_date'], 'hms_night_audits_property_date_unique');
            $table->index(['business_id', 'closed_at'], 'hms_night_audits_business_closed_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hms_night_audits');
        Schema::dropIfExists('hms_cashier_shifts');
        Schema::dropIfExists('hms_deposit_schedules');
        Schema::dropIfExists('hms_folio_entries');
        Schema::dropIfExists('hms_folios');
    }
};
