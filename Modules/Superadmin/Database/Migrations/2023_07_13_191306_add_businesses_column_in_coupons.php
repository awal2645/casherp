<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('superadmin_coupons') || Schema::hasColumn('superadmin_coupons', 'applied_on_business')) {
            return;
        }

        Schema::table('superadmin_coupons', function (Blueprint $table) {
            $table->string('applied_on_business')->nullable()->after('applied_on_packages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('superadmin_coupons', function (Blueprint $table) {
            $table->dropColumn('applied_on_business');
        });
    }
};
