<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('transaction_payments')) {
            return;
        }

        if (! Schema::hasColumn('transaction_payments', 'cheque_due_date')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $column = $table->date('cheque_due_date')->nullable();
                if (Schema::hasColumn('transaction_payments', 'cheque_number')) {
                    $column->after('cheque_number');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('transaction_payments') && Schema::hasColumn('transaction_payments', 'cheque_due_date')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropColumn('cheque_due_date');
            });
        }
    }
};
