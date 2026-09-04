<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'payment_dedupe_key')) {
                $table->string('payment_dedupe_key', 191)->nullable()->unique();
            }
            if (! Schema::hasColumn('subscriptions', 'currency_code')) {
                $table->string('currency_code', 3)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'payment_dedupe_key')) {
                $table->dropUnique(['payment_dedupe_key']);
            }
            $drops = array_values(array_filter([
                Schema::hasColumn('subscriptions', 'payment_dedupe_key') ? 'payment_dedupe_key' : null,
                Schema::hasColumn('subscriptions', 'currency_code') ? 'currency_code' : null,
            ]));
            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
