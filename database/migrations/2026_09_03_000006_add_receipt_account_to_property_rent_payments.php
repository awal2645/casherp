<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('property_rent_payments') || Schema::hasColumn('property_rent_payments', 'receipt_account_id')) {
            return;
        }

        Schema::table('property_rent_payments', function (Blueprint $table) {
            $column = $table->unsignedInteger('receipt_account_id')->nullable();
            if (Schema::hasColumn('property_rent_payments', 'method')) {
                $column->after('method');
            }
        });
    }

    public function down()
    {
        if (Schema::hasTable('property_rent_payments') && Schema::hasColumn('property_rent_payments', 'receipt_account_id')) {
            Schema::table('property_rent_payments', function (Blueprint $table) {
                $table->dropColumn('receipt_account_id');
            });
        }
    }
};
