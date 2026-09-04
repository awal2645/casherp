<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hms_properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('name');
            $table->string('code', 40);
            $table->string('public_slug')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->date('business_date');
            $table->time('default_check_in_time')->default('14:00:00');
            $table->time('default_check_out_time')->default('11:00:00');
            $table->boolean('booking_engine_enabled')->default(false);
            $table->boolean('channel_manager_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['business_id', 'code'], 'hms_properties_business_code_unique');
            $table->unique(['business_id', 'location_id'], 'hms_properties_business_location_unique');
            $table->unique('public_slug', 'hms_properties_public_slug_unique');
            $table->index(['business_id', 'is_active'], 'hms_properties_business_active_idx');
        });

        Schema::create('hms_rate_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->string('name');
            $table->string('code', 40);
            $table->string('meal_plan', 32)->default('room_only');
            $table->string('adjustment_type', 16)->default('fixed');
            $table->decimal('adjustment_value', 22, 4)->default(0);
            $table->unsignedSmallInteger('minimum_stay')->default(1);
            $table->unsignedSmallInteger('maximum_stay')->nullable();
            $table->unsignedSmallInteger('minimum_advance_days')->default(0);
            $table->unsignedSmallInteger('maximum_advance_days')->nullable();
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->decimal('deposit_percent', 8, 4)->default(0);
            $table->boolean('is_refundable')->default(true);
            $table->unsignedInteger('free_cancellation_hours')->default(24);
            $table->boolean('tax_inclusive')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('days_of_week')->nullable();
            $table->unsignedInteger('corporate_contact_id')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'hms_property_id', 'code'], 'hms_rate_plans_property_code_unique');
            $table->index(['business_id', 'is_active'], 'hms_rate_plans_business_active_idx');
        });

        Schema::create('hms_rate_plan_room_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_rate_plan_id');
            $table->unsignedBigInteger('hms_room_type_id');
            $table->unsignedSmallInteger('included_adults')->default(1);
            $table->unsignedSmallInteger('included_children')->default(0);
            $table->decimal('base_rate', 22, 4)->nullable();
            $table->decimal('extra_adult_rate', 22, 4)->default(0);
            $table->decimal('extra_child_rate', 22, 4)->default(0);
            $table->unsignedInteger('allotment')->nullable();
            $table->timestamps();

            $table->unique(['hms_rate_plan_id', 'hms_room_type_id'], 'hms_rate_plan_room_type_unique');
            $table->index(['business_id', 'hms_room_type_id'], 'hms_rate_plan_room_business_idx');
        });

        Schema::create('hms_guest_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('preferred_language', 12)->nullable();
            $table->string('nationality', 80)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('identity_document_type', 40)->nullable();
            $table->string('identity_document_last_four', 8)->nullable();
            $table->string('vip_level', 20)->nullable();
            $table->json('preferences')->nullable();
            $table->text('accessibility_needs')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->dateTime('consent_recorded_at')->nullable();
            $table->string('consent_source', 40)->nullable();
            $table->boolean('do_not_contact')->default(false);
            $table->date('retention_until')->nullable();
            $table->dateTime('anonymized_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'contact_id'], 'hms_guest_profiles_business_contact_unique');
            $table->index(['business_id', 'vip_level'], 'hms_guest_profiles_business_vip_idx');
        });

        Schema::create('hms_group_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_property_id');
            $table->unsignedInteger('organizer_contact_id')->nullable();
            $table->string('name');
            $table->string('code', 50);
            $table->string('status', 24)->default('tentative');
            $table->dateTime('arrival_at');
            $table->dateTime('departure_at');
            $table->dateTime('release_at')->nullable();
            $table->string('billing_instruction', 32)->default('individual');
            $table->decimal('deposit_required', 22, 4)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code'], 'hms_group_bookings_business_code_unique');
            $table->index(['business_id', 'hms_property_id', 'status'], 'hms_group_bookings_property_status_idx');
        });

        Schema::create('hms_group_room_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('hms_group_booking_id');
            $table->unsignedBigInteger('hms_room_id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->decimal('agreed_rate', 22, 4)->nullable();
            $table->string('status', 24)->default('held');
            $table->dateTime('release_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['hms_group_booking_id', 'hms_room_id'], 'hms_group_room_block_unique');
            $table->index(['business_id', 'hms_room_id', 'status'], 'hms_group_room_block_inventory_idx');
            $table->index(['business_id', 'transaction_id'], 'hms_group_room_block_booking_idx');
        });

        Schema::table('hms_room_types', function (Blueprint $table) {
            $table->unsignedBigInteger('hms_property_id')->nullable()->after('business_id');
            $table->index(['business_id', 'hms_property_id'], 'hms_room_types_property_idx');
        });

        Schema::table('hms_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('hms_property_id')->nullable()->after('hms_room_type_id');
            $table->index('hms_property_id', 'hms_rooms_property_idx');
        });

        Schema::table('hms_booking_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('hms_property_id')->nullable()->after('transaction_id');
            $table->index(['hms_property_id', 'hms_room_id'], 'hms_booking_lines_property_room_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('hms_property_id')->nullable()->after('hms_booking_status');
            $table->unsignedBigInteger('hms_rate_plan_id')->nullable()->after('hms_property_id');
            $table->unsignedBigInteger('hms_group_booking_id')->nullable()->after('hms_rate_plan_id');
            $table->unsignedBigInteger('hms_guest_profile_id')->nullable()->after('hms_group_booking_id');
            $table->index(['business_id', 'hms_property_id', 'hms_booking_status'], 'transactions_hms_property_status_idx');
        });

        $this->backfillProperties();
    }

    private function backfillProperties(): void
    {
        $businessIds = DB::table('hms_room_types')->select('business_id')->distinct()->pluck('business_id');

        foreach ($businessIds as $businessId) {
            $business = DB::table('business')->where('id', $businessId)->first();
            $location = DB::table('business_locations')->where('business_id', $businessId)->orderBy('id')->first();
            $name = $location->name ?? ($business->name ?? 'Hotel Property');
            $code = 'PROP-' . $businessId;
            $slug = Str::slug($name . '-' . $businessId);
            $propertyId = DB::table('hms_properties')->insertGetId([
                'business_id' => $businessId,
                'location_id' => $location->id ?? null,
                'currency_id' => $business->currency_id ?? null,
                'name' => $name,
                'code' => $code,
                'public_slug' => $slug,
                'timezone' => $business->time_zone ?? config('app.timezone', 'UTC'),
                'business_date' => now()->toDateString(),
                'is_active' => true,
                'created_by' => $business->owner_id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('hms_room_types')->where('business_id', $businessId)->update(['hms_property_id' => $propertyId]);

            $typeIds = DB::table('hms_room_types')->where('business_id', $businessId)->pluck('id');
            DB::table('hms_rooms')->whereIn('hms_room_type_id', $typeIds)->update(['hms_property_id' => $propertyId]);

            $transactionIds = DB::table('transactions')
                ->where('business_id', $businessId)
                ->where('type', 'hms_booking')
                ->pluck('id');
            DB::table('transactions')->whereIn('id', $transactionIds)->update(['hms_property_id' => $propertyId]);
            DB::table('hms_booking_lines')->whereIn('transaction_id', $transactionIds)->update(['hms_property_id' => $propertyId]);
        }
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_hms_property_status_idx');
            $table->dropColumn(['hms_property_id', 'hms_rate_plan_id', 'hms_group_booking_id', 'hms_guest_profile_id']);
        });
        Schema::table('hms_booking_lines', function (Blueprint $table) {
            $table->dropIndex('hms_booking_lines_property_room_idx');
            $table->dropColumn('hms_property_id');
        });
        Schema::table('hms_rooms', function (Blueprint $table) {
            $table->dropIndex('hms_rooms_property_idx');
            $table->dropColumn('hms_property_id');
        });
        Schema::table('hms_room_types', function (Blueprint $table) {
            $table->dropIndex('hms_room_types_property_idx');
            $table->dropColumn('hms_property_id');
        });

        Schema::dropIfExists('hms_group_room_blocks');
        Schema::dropIfExists('hms_group_bookings');
        Schema::dropIfExists('hms_guest_profiles');
        Schema::dropIfExists('hms_rate_plan_room_types');
        Schema::dropIfExists('hms_rate_plans');
        Schema::dropIfExists('hms_properties');
    }
};
