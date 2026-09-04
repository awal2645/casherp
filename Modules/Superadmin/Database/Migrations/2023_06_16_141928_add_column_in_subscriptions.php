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
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'original_price')) {
                $table->decimal('original_price', 22, 4)->after('package_price')->nullable();
            }
            if (! Schema::hasColumn('subscriptions', 'coupon_code')) {
                $table->string('coupon_code')->after('original_price')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'coupon_code']);
        });
    }
};
