<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        if (! Schema::hasColumn('transactions', 'expense_title')) {
            Schema::table('transactions', function (Blueprint $table) {
                $expenseTitle = $table->string('expense_title')->nullable();
                $paymentTo = $table->string('payment_to')->nullable();
                if (Schema::hasColumn('transactions', 'expense_sub_category_id')) {
                    $expenseTitle->after('expense_sub_category_id');
                    $paymentTo->after('expense_title');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'expense_title')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn(['expense_title', 'payment_to']);
            });
        }
    }
};
