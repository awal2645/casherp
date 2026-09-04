<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('essentials_leaves', function (Blueprint $table) {
            if (! Schema::hasColumn('essentials_leaves', 'changed_by')) {
                $table->unsignedInteger('changed_by')->nullable()->after('is_additional');
                $table->index('changed_by', 'essentials_leaves_changed_by_index');
            }

            $table->index(
                ['business_id', 'user_id', 'status', 'start_date'],
                'essentials_leaves_tenant_user_status_start_index'
            );
        });

        Schema::table('essentials_attendances', function (Blueprint $table) {
            $table->index(
                ['business_id', 'user_id', 'clock_in_time'],
                'essentials_attendance_tenant_user_in_index'
            );
            $table->index(
                ['business_id', 'user_id', 'clock_out_time'],
                'essentials_attendance_tenant_user_out_index'
            );
        });

        Schema::table('essentials_user_shifts', function (Blueprint $table) {
            $table->index(
                ['user_id', 'start_date', 'end_date'],
                'essentials_user_shift_dates_index'
            );
        });

        Schema::table('essentials_payroll_group_transactions', function (Blueprint $table) {
            $table->index('transaction_id', 'essentials_payroll_group_transaction_index');
        });
    }

    public function down()
    {
        Schema::table('essentials_payroll_group_transactions', function (Blueprint $table) {
            $table->dropIndex('essentials_payroll_group_transaction_index');
        });

        Schema::table('essentials_user_shifts', function (Blueprint $table) {
            $table->dropIndex('essentials_user_shift_dates_index');
        });

        Schema::table('essentials_attendances', function (Blueprint $table) {
            $table->dropIndex('essentials_attendance_tenant_user_in_index');
            $table->dropIndex('essentials_attendance_tenant_user_out_index');
        });

        Schema::table('essentials_leaves', function (Blueprint $table) {
            $table->dropIndex('essentials_leaves_tenant_user_status_start_index');
            if (Schema::hasColumn('essentials_leaves', 'changed_by')) {
                $table->dropIndex('essentials_leaves_changed_by_index');
                $table->dropColumn('changed_by');
            }
        });
    }
};
