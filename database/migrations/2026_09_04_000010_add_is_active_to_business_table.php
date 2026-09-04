<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business') && ! Schema::hasColumn('business', 'is_active')) {
            Schema::table('business', function (Blueprint $table) {
                $table->boolean('is_active')->default(1);
            });
        }

        if (Schema::hasTable('business_locations') && ! Schema::hasColumn('business_locations', 'is_active')) {
            Schema::table('business_locations', function (Blueprint $table) {
                $table->boolean('is_active')->default(1);
            });
        }
    }

    public function down(): void
    {
        // Keep the column; existing POS installs rely on it.
    }
};
