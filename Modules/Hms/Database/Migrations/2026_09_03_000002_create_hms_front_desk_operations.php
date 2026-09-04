<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('hms_booking_status', 32)->nullable()->after('status');
            $table->string('hms_booking_source', 32)->nullable()->after('hms_booking_status');
            $table->string('hms_external_reference')->nullable()->after('hms_booking_source');
            $table->dateTime('hms_hold_expires_at')->nullable()->after('hms_external_reference');
            $table->text('hms_special_requests')->nullable()->after('hms_hold_expires_at');
            $table->text('hms_cancellation_reason')->nullable()->after('hms_special_requests');
            $table->dateTime('hms_cancelled_at')->nullable()->after('hms_cancellation_reason');
            $table->dateTime('hms_no_show_at')->nullable()->after('hms_cancelled_at');
            $table->dateTime('hms_last_status_changed_at')->nullable()->after('hms_no_show_at');

            $table->index(
                ['business_id', 'type', 'hms_booking_status'],
                'transactions_hms_business_status_idx'
            );
            $table->index(
                ['business_id', 'hms_booking_arrival_date_time', 'hms_booking_departure_date_time'],
                'transactions_hms_business_stay_idx'
            );
        });

        Schema::create('hms_booking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('transaction_id');
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['business_id', 'transaction_id', 'occurred_at'],
                'hms_booking_events_booking_timeline_idx'
            );
            $table->index(
                ['business_id', 'event_type', 'occurred_at'],
                'hms_booking_events_business_type_idx'
            );
        });

        DB::table('transactions')
            ->where('type', 'hms_booking')
            ->update([
                'hms_booking_status' => DB::raw("CASE
                    WHEN status = 'cancelled' THEN 'cancelled'
                    WHEN check_out IS NOT NULL THEN 'checked_out'
                    WHEN check_in IS NOT NULL THEN 'checked_in'
                    WHEN status = 'pending' THEN 'tentative'
                    ELSE 'reserved'
                END"),
                'hms_booking_source' => 'direct',
                'hms_last_status_changed_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down()
    {
        Schema::dropIfExists('hms_booking_events');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_hms_business_status_idx');
            $table->dropIndex('transactions_hms_business_stay_idx');
            $table->dropColumn([
                'hms_booking_status',
                'hms_booking_source',
                'hms_external_reference',
                'hms_hold_expires_at',
                'hms_special_requests',
                'hms_cancellation_reason',
                'hms_cancelled_at',
                'hms_no_show_at',
                'hms_last_status_changed_at',
            ]);
        });
    }
};
