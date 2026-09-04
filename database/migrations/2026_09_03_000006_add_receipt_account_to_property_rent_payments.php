<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('property_rent_payments', function (Blueprint $table) {
            $table->unsignedInteger('receipt_account_id')->nullable()->after('method');
        });
    }

    public function down()
    {
        Schema::table('property_rent_payments', function (Blueprint $table) {
            $table->dropColumn('receipt_account_id');
        });
    }
};
