<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('business') && ! Schema::hasColumn('business', 'onboarding_settings')) {
            Schema::table('business', function (Blueprint $table) {
                $table->json('onboarding_settings')->nullable()->after('industry_id');
                $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_settings');
            });
        }

        $descriptions = [
            'general_business' => 'For shops, wholesalers, distributors, and general merchants.',
            'restaurant_food_service' => 'For dine-in, takeaway, delivery, cafes, bars, and catering.',
            'hotel_lodge_guesthouse' => 'For accommodation-first hotels, lodges, guest houses, and hostels.',
            'hotel_with_restaurant' => 'For accommodation businesses with food, bar, or room service.',
            'property_management_rentals' => 'For property owners, real estate agencies, developers, facilities teams, estate managers, and third-party property managers.',
            'professional_services' => 'For consultancies, agencies, studios, and client or project-led firms.',
        ];

        foreach ($descriptions as $code => $description) {
            DB::table('industries')->where('code', $code)->update([
                'description' => $description,
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            $table->dropColumn(['onboarding_settings', 'onboarding_completed_at']);
        });
    }
};
