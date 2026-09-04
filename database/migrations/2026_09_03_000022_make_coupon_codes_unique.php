<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('superadmin_coupons')) {
            return;
        }

        $duplicate = DB::table('superadmin_coupons')
            ->selectRaw('LOWER(coupon_code) as normalized_code, COUNT(*) as aggregate')
            ->groupByRaw('LOWER(coupon_code)')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new \RuntimeException('Duplicate coupon codes must be resolved before the CashERP pricing migration can continue.');
        }

        Schema::table('superadmin_coupons', function (Blueprint $table) {
            $table->unique('coupon_code', 'superadmin_coupons_coupon_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('superadmin_coupons', function (Blueprint $table) {
            $table->dropUnique('superadmin_coupons_coupon_code_unique');
        });
    }
};
