<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('subscriptions') || Schema::hasColumn('subscriptions', 'covered_by_subscription_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('covered_by_subscription_id')->nullable()->after('package_id');
            $table->index('covered_by_subscription_id');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('subscriptions') || ! Schema::hasColumn('subscriptions', 'covered_by_subscription_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['covered_by_subscription_id']);
            $table->dropColumn('covered_by_subscription_id');
        });
    }
};
