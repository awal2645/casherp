<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('packages') || Schema::hasColumn('packages', 'mark_package_as_popular')) {
            return;
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('mark_package_as_popular')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('mark_package_as_popular');
        });
    }
};
