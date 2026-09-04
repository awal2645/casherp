<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure every launch industry can use the independent HRM workspace.
     *
     * Super Admin may still change an industry's default profile after this
     * one-time repair. Existing explicit per-company overrides are preserved.
     */
    public function up()
    {
        if (! Schema::hasTable('industries')
            || ! Schema::hasTable('features')
            || ! Schema::hasTable('industry_features')) {
            return;
        }

        $now = now();
        $industryCodes = [
            'general_business',
            'restaurant_food_service',
            'hotel_lodge_guesthouse',
            'hotel_with_restaurant',
            'property_management_rentals',
            'professional_services',
        ];

        $hrmFeatureId = DB::table('features')->where('code', 'hrm')->value('id');

        if (empty($hrmFeatureId)) {
            $hrmFeatureId = DB::table('features')->insertGetId([
                'code' => 'hrm',
                'name' => 'Human Resource Management (HRM)',
                'module_key' => 'Essentials',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('features')->where('id', $hrmFeatureId)->update([
                'name' => 'Human Resource Management (HRM)',
                'module_key' => 'Essentials',
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }

        $industryIds = DB::table('industries')
            ->whereIn('code', $industryCodes)
            ->pluck('id');

        foreach ($industryIds as $industryId) {
            DB::table('industry_features')->updateOrInsert(
                ['industry_id' => $industryId, 'feature_id' => $hrmFeatureId],
                ['enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if (! Schema::hasTable('business_features') || ! Schema::hasColumn('business', 'industry_id')) {
            return;
        }

        DB::table('business')
            ->whereIn('industry_id', $industryIds)
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($businesses) use ($hrmFeatureId, $now) {
                foreach ($businesses as $business) {
                    $existing = DB::table('business_features')
                        ->where('business_id', $business->id)
                        ->where('feature_id', $hrmFeatureId)
                        ->first();

                    // Do not overwrite an explicit Super Admin company-level choice.
                    if ($existing && $existing->source !== 'industry_default') {
                        continue;
                    }

                    DB::table('business_features')->updateOrInsert(
                        ['business_id' => $business->id, 'feature_id' => $hrmFeatureId],
                        [
                            'is_enabled' => true,
                            'source' => 'industry_default',
                            'created_at' => $existing->created_at ?? $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    /**
     * This is a forward-only data repair. Rolling it back must not silently
     * remove HRM from companies or profiles configured after deployment.
     */
    public function down()
    {
        // Intentionally non-destructive.
    }
};
