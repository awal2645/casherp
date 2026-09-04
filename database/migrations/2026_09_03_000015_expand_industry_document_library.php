<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('industries')
            || ! Schema::hasTable('features')
            || ! Schema::hasTable('industry_features')
            || ! Schema::hasTable('document_types')
            || ! Schema::hasTable('industry_document_types')) {
            return;
        }

        $now = now();
        $industries = [
            'retail_wholesale' => ['name' => 'Retail & Wholesale', 'description' => 'For retailers, wholesalers, distributors, supermarkets, and multi-branch stores.', 'sort_order' => 70],
            'construction_contracting' => ['name' => 'Construction & Contracting', 'description' => 'For builders, contractors, engineers, quantity surveyors, and project delivery firms.', 'sort_order' => 80],
            'healthcare_clinic' => ['name' => 'Healthcare & Clinics', 'description' => 'For clinics, medical centres, dental practices, laboratories, and wellness providers.', 'sort_order' => 90],
            'education_training' => ['name' => 'Education & Training', 'description' => 'For schools, colleges, academies, tutors, and professional training providers.', 'sort_order' => 100],
            'automotive_services' => ['name' => 'Automotive Services', 'description' => 'For workshops, garages, parts and service centres, and vehicle repair businesses.', 'sort_order' => 110],
            'logistics_transport' => ['name' => 'Logistics & Transport', 'description' => 'For freight, courier, delivery, haulage, fleet, and transport service providers.', 'sort_order' => 120],
            'nonprofit_membership' => ['name' => 'Nonprofit & Membership', 'description' => 'For charities, associations, clubs, foundations, and membership organisations.', 'sort_order' => 130],
        ];

        foreach ($industries as $code => $industry) {
            DB::table('industries')->updateOrInsert(
                ['code' => $code],
                $industry + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
        DB::table('industries')->where('code', 'general_business')->update([
            'description' => 'For mixed trading, distribution, and other businesses that do not need a specialist operational workspace.',
            'updated_at' => $now,
        ]);

        $featureProfiles = [
            'retail_wholesale' => ['pos', 'purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'smart_documents'],
            'construction_contracting' => ['purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'crm', 'projects', 'assets', 'procurement', 'smart_documents'],
            'healthcare_clinic' => ['expenses', 'accounting', 'hrm', 'crm', 'assets', 'smart_documents'],
            'education_training' => ['expenses', 'accounting', 'hrm', 'crm', 'projects', 'assets', 'smart_documents'],
            'automotive_services' => ['pos', 'purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'crm', 'assets', 'smart_documents'],
            'logistics_transport' => ['purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'crm', 'assets', 'smart_documents'],
            'nonprofit_membership' => ['expenses', 'accounting', 'hrm', 'crm', 'projects', 'assets', 'smart_documents'],
        ];
        $industryIds = DB::table('industries')->pluck('id', 'code');
        $featureIds = DB::table('features')->pluck('id', 'code');
        foreach ($featureProfiles as $industryCode => $featureCodes) {
            foreach ($featureCodes as $featureCode) {
                if (empty($industryIds[$industryCode]) || empty($featureIds[$featureCode])) {
                    continue;
                }
                DB::table('industry_features')->updateOrInsert(
                    ['industry_id' => $industryIds[$industryCode], 'feature_id' => $featureIds[$featureCode]],
                    ['enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $typeIds = DB::table('document_types')->pluck('id', 'code')->all();
        $sort = (int) DB::table('document_types')->max('sort_order');
        foreach ((array) config('smart_documents.types', []) as $code => $definition) {
            if (isset($typeIds[$code])) {
                continue;
            }
            $sort += 10;
            $typeIds[$code] = DB::table('document_types')->insertGetId([
                'code' => $code,
                'name' => $definition['name'],
                'default_title' => $definition['title'],
                'category' => $definition['category'],
                'default_prefix' => $definition['prefix'],
                'scenario_code' => $definition['scenario'],
                'schema_key' => $definition['schema'] ?? null,
                'convert_to_code' => $definition['convert_to'] ?? null,
                'output_format' => $definition['output_format'] ?? 'a4',
                'supports_line_items' => $definition['supports_lines'] ?? true,
                'is_financial' => $definition['is_financial'] ?? false,
                'requires_acceptance' => $definition['requires_acceptance'] ?? false,
                'is_active' => true,
                'sort_order' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ((array) config('smart_documents.industry_profiles', []) as $industryCode => $codes) {
            if (empty($industryIds[$industryCode])) {
                continue;
            }
            foreach (array_values($codes) as $index => $typeCode) {
                if (empty($typeIds[$typeCode])) {
                    continue;
                }
                $exists = DB::table('industry_document_types')
                    ->where('industry_id', $industryIds[$industryCode])
                    ->where('document_type_id', $typeIds[$typeCode])
                    ->exists();
                if (! $exists) {
                    DB::table('industry_document_types')->insert([
                        'industry_id' => $industryIds[$industryCode],
                        'document_type_id' => $typeIds[$typeCode],
                        'enabled_by_default' => true,
                        'sort_order' => ($index + 1) * 10,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('business_document_settings') && Schema::hasColumn('business', 'industry_id') && Schema::hasColumn('business', 'enabled_modules')) {
            DB::table('business')->whereNotNull('industry_id')->select(['id', 'industry_id', 'enabled_modules'])
                ->orderBy('id')->chunkById(100, function ($businesses) use ($industryIds, $typeIds, $now) {
                    $codeByIndustryId = collect($industryIds)->flip();
                    foreach ($businesses as $business) {
                        $industryCode = $codeByIndustryId->get($business->industry_id);
                        foreach ((array) config('smart_documents.industry_profiles.'.$industryCode, []) as $typeCode) {
                            if (empty($typeIds[$typeCode])) {
                                continue;
                            }
                            $exists = DB::table('business_document_settings')
                                ->where('business_id', $business->id)
                                ->where('document_type_id', $typeIds[$typeCode])
                                ->exists();
                            if (! $exists) {
                                DB::table('business_document_settings')->insert([
                                    'business_id' => $business->id,
                                    'document_type_id' => $typeIds[$typeCode],
                                    'is_enabled' => true,
                                    'source' => 'industry_default',
                                    'prefix' => config('smart_documents.types.'.$typeCode.'.prefix', strtoupper(substr($typeCode, 0, 6))),
                                    'next_number' => 1,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);
                            }
                        }

                        $modules = json_decode($business->enabled_modules ?: '[]', true) ?: [];
                        if (! in_array('add_sale', $modules, true)) {
                            $modules[] = 'add_sale';
                            DB::table('business')->where('id', $business->id)->update([
                                'enabled_modules' => json_encode(array_values(array_unique($modules))),
                            ]);
                        }
                    }
                });
        }

        $permissions = array_map(
            fn ($code) => 'smart_documents.type.'.$code,
            array_keys((array) config('smart_documents.types', []))
        );
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        Cache::forget('spatie.permission.cache');
    }

    public function down()
    {
        // Forward-only catalogue expansion. Removing industries, document
        // types or permissions could orphan issued records and role grants.
    }
};
