<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('property_maintenance_tickets', function (Blueprint $table) {
            $table->unsignedInteger('payment_account_id')->nullable()->after('actual_cost');
            $table->timestamp('completed_at')->nullable()->after('resolved_on');
            $table->unsignedInteger('completed_by')->nullable()->after('completed_at');
        });
    }

    public function down()
    {
        Schema::table('property_maintenance_tickets', function (Blueprint $table) {
            $table->dropColumn(['payment_account_id', 'completed_at', 'completed_by']);
        });
    }
};
