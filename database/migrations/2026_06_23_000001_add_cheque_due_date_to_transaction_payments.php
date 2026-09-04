<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cheque due date is part of the core POS so the payment screens keep
     * working even when the Cheque module is not installed. The Cheque
     * module builds its extra features (status, assignee, cleared date)
     * on top of this column.
     */
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
