<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
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
            && ! $this->foreignKeyExists('cash_register_transactions', 'transaction_payment_id')
        ) {
            Schema::table('cash_register_transactions', function (Blueprint $table) {
                $table->foreign('transaction_payment_id')
                      ->references('id')
                      ->on('transaction_payments')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('cash_register_transactions')) {
            return;
        }

        if ($this->foreignKeyExists('cash_register_transactions', 'transaction_payment_id')) {
            Schema::table('cash_register_transactions', function (Blueprint $table) {
                $table->dropForeign(['transaction_payment_id']);
            });
        }

        if (Schema::hasColumn('cash_register_transactions', 'transaction_payment_id')) {
            Schema::table('cash_register_transactions', function (Blueprint $table) {
                $table->dropColumn('transaction_payment_id');
            });
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = Schema::getConnection()->selectOne(
            'select constraint_name from information_schema.key_column_usage
             where table_schema = ? and table_name = ? and column_name = ?
               and referenced_table_name is not null
             limit 1',
            [$database, $table, $column]
        );

        return ! empty($row);
    }
};
