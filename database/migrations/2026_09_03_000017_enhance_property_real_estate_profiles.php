<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('industries')) {
            DB::table('industries')
                ->where('code', 'property_management_rentals')
                ->update([
                    'name' => 'Property Management & Real Estate',
                    'description' => 'For owners, landlords, agencies, developers, facilities teams, estate managers, and third-party property managers.',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('properties')
            && ! Schema::hasColumn('properties', 'business_location_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->unsignedInteger('business_location_id')->nullable()->after('business_id')->index();
                $table->string('portfolio_category', 60)->nullable()->after('type');
                $table->string('property_subtype', 80)->nullable()->after('portfolio_category');
                $table->foreign('business_location_id')
                    ->references('id')
                    ->on('business_locations')
                    ->nullOnDelete();
            });

            DB::table('properties')->orderBy('id')->chunkById(100, function ($properties) {
                $locationByBusiness = [];
                foreach ($properties as $property) {
                    if (! array_key_exists($property->business_id, $locationByBusiness)) {
                        $locationByBusiness[$property->business_id] = DB::table('business_locations')
                            ->where('business_id', $property->business_id)
                            ->whereNull('deleted_at')
                            ->orderBy('id')
                            ->value('id');
                    }

                    $category = in_array($property->type, ['residential', 'commercial', 'mixed_use', 'industrial_logistics', 'land', 'community_association'], true)
                        ? $property->type
                        : ($property->type === 'mixed' ? 'mixed_use' : 'mixed_portfolio');

                    DB::table('properties')->where('id', $property->id)->update([
                        'business_location_id' => $locationByBusiness[$property->business_id],
                        'portfolio_category' => $category,
                        'property_subtype' => $property->type ?: 'other',
                    ]);
                }
            });
        }

        if (! Schema::hasTable('property_dashboard_preferences')) {
            Schema::create('property_dashboard_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('default_business_location_id')->nullable();
                $table->unsignedBigInteger('default_property_id')->nullable();
                $table->json('visible_widgets')->nullable();
                $table->json('widget_order')->nullable();
                $table->boolean('compact_mode')->default(false);
                $table->timestamps();

                $table->unique(['business_id', 'user_id'], 'property_dashboard_user_unique');
                $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('default_business_location_id', 'property_dashboard_location_fk')
                    ->references('id')->on('business_locations')->nullOnDelete();
                $table->foreign('default_property_id', 'property_dashboard_property_fk')
                    ->references('id')->on('properties')->nullOnDelete();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('property_dashboard_preferences');

        if (Schema::hasTable('properties')
            && Schema::hasColumn('properties', 'business_location_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropForeign(['business_location_id']);
                $table->dropColumn(['business_location_id', 'portfolio_category', 'property_subtype']);
            });
        }

        if (Schema::hasTable('industries')) {
            DB::table('industries')
                ->where('code', 'property_management_rentals')
                ->where('name', 'Property Management & Real Estate')
                ->update([
                    'name' => 'Property Management & Rentals',
                    'updated_at' => now(),
                ]);
        }
    }
};
