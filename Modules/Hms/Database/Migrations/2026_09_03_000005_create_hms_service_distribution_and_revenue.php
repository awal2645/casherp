<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hms_operational_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->unsignedBigInteger('hms_room_id')->nullable();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->string('task_type', 32);
            $table->string('status', 24)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->string('item_name')->nullable();
            $table->decimal('quantity', 22, 4)->nullable();
            $table->decimal('charge_amount', 22, 4)->default(0);
            $table->dateTime('occurred_at');
            $table->dateTime('due_at')->nullable();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('custody_reference', 80)->nullable();
            $table->text('description')->nullable();
            $table->text('resolution')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['business_id', 'hms_property_id', 'status'], 'hms_operational_tasks_property_status_idx');
            $table->index(['business_id', 'task_type', 'occurred_at'], 'hms_operational_tasks_type_date_idx');
        });

        Schema::create('hms_operational_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_operational_task_id');
            $table->string('event_type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['business_id', 'hms_operational_task_id', 'occurred_at'], 'hms_operational_events_task_timeline_idx');
        });

        Schema::create('hms_channel_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->string('provider', 40);
            $table->string('name');
            $table->string('public_key', 80);
            $table->text('secret_encrypted');
            $table->string('status', 20)->default('active');
            $table->dateTime('last_inventory_sync_at')->nullable();
            $table->dateTime('last_reservation_sync_at')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique('public_key', 'hms_channel_connections_public_key_unique');
            $table->unique(['business_id', 'hms_property_id', 'provider', 'name'], 'hms_channel_connections_identity_unique');
            $table->index(['business_id', 'status'], 'hms_channel_connections_business_status_idx');
        });

        Schema::create('hms_channel_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_channel_connection_id');
            $table->string('local_type', 32);
            $table->unsignedBigInteger('local_id');
            $table->string('external_type', 32);
            $table->string('external_id', 120);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['hms_channel_connection_id', 'external_type', 'external_id'], 'hms_channel_mappings_external_unique');
            $table->unique(['hms_channel_connection_id', 'local_type', 'local_id'], 'hms_channel_mappings_local_unique');
            $table->index('business_id', 'hms_channel_mappings_business_idx');
        });

        Schema::create('hms_channel_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_channel_connection_id');
            $table->string('direction', 12);
            $table->string('event_type', 60);
            $table->string('external_event_id', 160)->nullable();
            $table->string('idempotency_key', 190);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 24)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'hms_channel_messages_idempotency_unique');
            $table->index(['business_id', 'status', 'created_at'], 'hms_channel_messages_status_idx');
        });

        Schema::create('hms_guest_message_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id')->nullable();
            $table->string('name');
            $table->string('event_type', 32);
            $table->string('channel', 16)->default('email');
            $table->integer('timing_offset_minutes')->default(0);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('requires_marketing_consent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['business_id', 'event_type', 'is_active'], 'hms_guest_message_rules_event_idx');
        });

        Schema::create('hms_guest_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_guest_message_rule_id');
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('contact_id');
            $table->string('channel', 16);
            $table->string('status', 20)->default('queued');
            $table->string('idempotency_key', 190);
            $table->dateTime('scheduled_for');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'hms_guest_message_logs_idempotency_unique');
            $table->index(['business_id', 'status', 'scheduled_for'], 'hms_guest_message_logs_delivery_idx');
        });

        Schema::create('hms_revenue_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->date('month');
            $table->decimal('room_revenue_budget', 22, 4)->default(0);
            $table->decimal('occupancy_budget', 8, 4)->default(0);
            $table->decimal('adr_budget', 22, 4)->default(0);
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->unique(['business_id', 'hms_property_id', 'month'], 'hms_revenue_budgets_property_month_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hms_revenue_budgets');
        Schema::dropIfExists('hms_guest_message_logs');
        Schema::dropIfExists('hms_guest_message_rules');
        Schema::dropIfExists('hms_channel_messages');
        Schema::dropIfExists('hms_channel_mappings');
        Schema::dropIfExists('hms_channel_connections');
        Schema::dropIfExists('hms_operational_events');
        Schema::dropIfExists('hms_operational_tasks');
    }
};
