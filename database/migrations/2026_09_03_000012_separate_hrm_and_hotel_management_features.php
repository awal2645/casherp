<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('features')) {
            return;
        }

        $now = now();

        // HRM and HMS are intentionally separate feature records. A business
        // may enable either or both without one feature controlling the other.
        $features = [
            'hrm' => [
                'name' => 'Human Resource Management (HRM)',
                'module_key' => 'Essentials',
            ],
            'hms' => [
                'name' => 'Hotel Management System (HMS)',
                'module_key' => 'Hms',
            ],
        ];

        foreach ($features as $code => $attributes) {
            $existing = DB::table('features')->where('code', $code);
            $values = $attributes + ['updated_at' => $now];

            if ($existing->exists()) {
                // Preserve the administrator's existing active/inactive choice.
                $existing->update($values);
            } else {
                DB::table('features')->insert($values + [
                    'code' => $code,
                    'is_active' => true,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if (! Schema::hasTable('features')) {
            return;
        }

        DB::table('features')->where('code', 'hrm')->update([
            'name' => 'HRM & Essentials',
            'updated_at' => now(),
        ]);

        DB::table('features')->where('code', 'hms')->update([
            'name' => 'Hotel Management',
            'updated_at' => now(),
        ]);
    }
};
