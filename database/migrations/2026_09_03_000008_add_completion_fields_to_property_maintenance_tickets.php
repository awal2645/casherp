<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('property_maintenance_tickets')) {
            return;
        }

        Schema::table('property_maintenance_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('property_maintenance_tickets', 'payment_account_id')) {
                $table->unsignedInteger('payment_account_id')->nullable();
            }
            if (! Schema::hasColumn('property_maintenance_tickets', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (! Schema::hasColumn('property_maintenance_tickets', 'completed_by')) {
                $table->unsignedInteger('completed_by')->nullable();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('property_maintenance_tickets')) {
            return;
        }

        $drops = array_values(array_filter([
            Schema::hasColumn('property_maintenance_tickets', 'payment_account_id') ? 'payment_account_id' : null,
            Schema::hasColumn('property_maintenance_tickets', 'completed_at') ? 'completed_at' : null,
            Schema::hasColumn('property_maintenance_tickets', 'completed_by') ? 'completed_by' : null,
        ]));

        if ($drops) {
            Schema::table('property_maintenance_tickets', function (Blueprint $table) use ($drops) {
                $table->dropColumn($drops);
            });
        }
    }
};
