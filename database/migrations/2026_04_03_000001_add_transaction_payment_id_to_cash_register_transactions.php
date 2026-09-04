<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cash_register_transactions')) {
            return;
        }

        if (! Schema::hasColumn('cash_register_transactions', 'transaction_payment_id')) {
            Schema::table('cash_register_transactions', function (Blueprint $table) {
                $column = $table->unsignedInteger('transaction_payment_id')->nullable();
                if (Schema::hasColumn('cash_register_transactions', 'transaction_id')) {
                    $column->after('transaction_id');
                }
            });
        }

        if (
            Schema::hasTable('transaction_payments')
            && Schema::hasColumn('cash_register_transactions', 'transaction_payment_id')
        ) {
            try {
                Schema::table('cash_register_transactions', function (Blueprint $table) {
                    $table->foreign('transaction_payment_id')
                        ->references('id')
                        ->on('transaction_payments')
                        ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // Foreign key may already exist on a full CashERP database.
            }
        }
    }

    public function down()
    {
        if (! Schema::hasTable('cash_register_transactions')) {
            return;
        }

        if (Schema::hasColumn('cash_register_transactions', 'transaction_payment_id')) {
            Schema::table('cash_register_transactions', function (Blueprint $table) {
                try {
                    $table->dropForeign(['transaction_payment_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('transaction_payment_id');
            });
        }
    }
};
