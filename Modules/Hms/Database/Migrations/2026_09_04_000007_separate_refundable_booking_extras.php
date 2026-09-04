<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hms_extras') && ! Schema::hasColumn('hms_extras', 'financial_classification')) {
            Schema::table('hms_extras', function (Blueprint $table) {
                $table->string('financial_classification', 40)
                    ->default('revenue')
                    ->after('price_per')
                    ->index();
            });

            // Only classify the reusable master record. Historic booking
            // snapshots remain revenue until a booking is intentionally
            // edited, avoiding silent changes to closed invoices.
            DB::table('hms_extras')
                ->whereRaw('LOWER(name) LIKE ?', ['%security%'])
                ->whereRaw('LOWER(name) LIKE ?', ['%deposit%'])
                ->update(['financial_classification' => 'refundable_security_deposit']);
        }

        if (Schema::hasTable('hms_booking_extras') && ! Schema::hasColumn('hms_booking_extras', 'financial_classification')) {
            Schema::table('hms_booking_extras', function (Blueprint $table) {
                $table->string('financial_classification', 40)
                    ->default('revenue')
                    ->after('price')
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hms_booking_extras') && Schema::hasColumn('hms_booking_extras', 'financial_classification')) {
            Schema::table('hms_booking_extras', function (Blueprint $table) {
                $table->dropIndex(['financial_classification']);
                $table->dropColumn('financial_classification');
            });
        }
        if (Schema::hasTable('hms_extras') && Schema::hasColumn('hms_extras', 'financial_classification')) {
            Schema::table('hms_extras', function (Blueprint $table) {
                $table->dropIndex(['financial_classification']);
                $table->dropColumn('financial_classification');
            });
        }
    }
};
