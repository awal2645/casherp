<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first overlay login baseline created a thin `business` table.
 * Later overlay migrations read Ultimate POS columns such as
 * enabled_modules. Add those columns when they are missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business')) {
            return;
        }

        $textColumns = [
            'enabled_modules',
            'common_settings',
            'pos_settings',
            'email_settings',
            'sms_settings',
            'keyboard_shortcuts',
            'repair_settings',
            'ref_no_prefixes',
            'productcatalogue_settings',
            'weighing_scale_setting',
        ];

        Schema::table('business', function (Blueprint $table) use ($textColumns) {
            foreach ($textColumns as $column) {
                if (! Schema::hasColumn('business', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
    }
};
