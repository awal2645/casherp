<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('module_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('industry_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('industries')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->boolean('enabled_by_default')->default(true);
            $table->timestamps();
            $table->unique(['industry_id', 'feature_id']);
        });

        Schema::create('business_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('source')->default('industry_default');
            $table->timestamps();
            $table->unique(['business_id', 'feature_id']);
            $table->index(['business_id', 'is_enabled']);
        });

        if (Schema::hasTable('business') && ! Schema::hasColumn('business', 'industry_id')) {
            Schema::table('business', function (Blueprint $table) {
                $table->foreignId('industry_id')->nullable()->after('owner_id')->constrained('industries')->nullOnDelete();
            });
        }

        // Super Admin already uses location_count for branches per company. This
        // additional field governs the number of companies an account may own.
        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'max_businesses')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->unsignedInteger('max_businesses')->default(1)->after('location_count')
                    ->comment('Maximum companies owned by one account; 0 means unlimited.');
            });
        }

        $now = now();
        $industries = [
            ['code' => 'general_business', 'name' => 'General Business & Trading', 'sort_order' => 10],
            ['code' => 'restaurant_food_service', 'name' => 'Restaurant, Cafe & Fast Food', 'sort_order' => 20],
            ['code' => 'hotel_lodge_guesthouse', 'name' => 'Hotel, Lodge & Guest House', 'sort_order' => 30],
            ['code' => 'hotel_with_restaurant', 'name' => 'Hotel / Lodge with Restaurant', 'sort_order' => 40],
            ['code' => 'property_management_rentals', 'name' => 'Property Management & Real Estate', 'sort_order' => 50],
            ['code' => 'professional_services', 'name' => 'Professional Services', 'sort_order' => 60],
        ];
        DB::table('industries')->insert(array_map(fn ($industry) => $industry + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], $industries));

        $features = [
            ['code' => 'pos', 'name' => 'POS & Sales', 'module_key' => 'pos_sale'],
            ['code' => 'purchases', 'name' => 'Purchases', 'module_key' => 'purchases'],
            ['code' => 'inventory', 'name' => 'Inventory', 'module_key' => 'stock_transfers'],
            ['code' => 'expenses', 'name' => 'Expenses', 'module_key' => 'expenses'],
            ['code' => 'accounting', 'name' => 'Accounting', 'module_key' => 'Accounting'],
            ['code' => 'hrm', 'name' => 'Human Resource Management (HRM)', 'module_key' => 'Essentials'],
            ['code' => 'hms', 'name' => 'Hotel Management System (HMS)', 'module_key' => 'Hms'],
            ['code' => 'restaurant_operations', 'name' => 'Restaurant Operations', 'module_key' => 'restaurant'],
            ['code' => 'crm', 'name' => 'CRM', 'module_key' => 'Crm'],
            ['code' => 'projects', 'name' => 'Projects', 'module_key' => 'Project'],
            ['code' => 'assets', 'name' => 'Asset Management', 'module_key' => 'AssetManagement'],
            ['code' => 'property_management', 'name' => 'Property Management', 'module_key' => 'PropertyManagement'],
        ];
        DB::table('features')->insert(array_map(fn ($feature) => $feature + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now], $features));

        $industryIds = DB::table('industries')->pluck('id', 'code');
        $featureIds = DB::table('features')->pluck('id', 'code');
        $profiles = [
            'general_business' => ['pos', 'purchases', 'inventory', 'expenses', 'accounting', 'hrm'],
            'restaurant_food_service' => ['pos', 'purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'restaurant_operations'],
            'hotel_lodge_guesthouse' => ['hms', 'expenses', 'accounting', 'hrm', 'assets'],
            'hotel_with_restaurant' => ['hms', 'pos', 'purchases', 'inventory', 'expenses', 'accounting', 'hrm', 'assets', 'restaurant_operations'],
            'property_management_rentals' => ['property_management', 'expenses', 'accounting', 'hrm', 'crm', 'assets'],
            'professional_services' => ['crm', 'projects', 'expenses', 'accounting', 'hrm'],
        ];
        $rows = [];
        foreach ($profiles as $industry => $featureCodes) {
            foreach ($featureCodes as $featureCode) {
                $rows[] = ['industry_id' => $industryIds[$industry], 'feature_id' => $featureIds[$featureCode], 'enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('industry_features')->insert($rows);
    }

    public function down()
    {
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('max_businesses'));
        $driver = DB::connection()->getDriverName();
        Schema::table('business', function (Blueprint $table) use ($driver) {
            if ($driver === 'sqlite') {
                $table->dropColumn('industry_id');
            } else {
                $table->dropConstrainedForeignId('industry_id');
            }
        });
        Schema::dropIfExists('business_features');
        Schema::dropIfExists('industry_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('industries');
    }
};
