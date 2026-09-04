<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $this->createSettings();
        $this->createKitchen();
        $this->createRecipesAndInventoryLedger();
        $this->createFulfilmentAndReservations();
        $this->createRegisterControl();
        $this->provisionAccess();
    }

    private function createSettings(): void
    {
        if (! Schema::hasTable('restaurant_operation_settings')) {
            Schema::create('restaurant_operation_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->unique();
                $table->json('enabled_channels')->nullable();
                $table->json('reservation_policy')->nullable();
                $table->json('kitchen_policy')->nullable();
                $table->json('inventory_policy')->nullable();
                $table->json('register_policy')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    private function createKitchen(): void
    {
        if (! Schema::hasTable('restaurant_kitchen_stations')) {
            Schema::create('restaurant_kitchen_stations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->string('name');
                $table->string('code', 40);
                $table->string('station_type', 40)->default('main');
                $table->unsignedInteger('printer_id')->nullable();
                $table->unsignedInteger('service_level_minutes')->default(15);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['business_id', 'location_id', 'code'], 'rest_station_scope_unique');
                $table->index(['business_id', 'location_id', 'is_active'], 'rest_station_active_index');
            });
        }

        if (! Schema::hasTable('restaurant_product_stations')) {
            Schema::create('restaurant_product_stations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('station_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('variation_id')->nullable();
                $table->unsignedInteger('preparation_minutes')->nullable();
                $table->unsignedInteger('priority')->default(0);
                $table->timestamps();
                $table->unique(['business_id', 'station_id', 'product_id', 'variation_id'], 'rest_product_station_unique');
                $table->index(['business_id', 'product_id', 'variation_id'], 'rest_product_station_lookup');
            });
        }

        if (! Schema::hasTable('restaurant_kitchen_tickets')) {
            Schema::create('restaurant_kitchen_tickets', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('transaction_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('ticket_number', 80);
                $table->unsignedInteger('sequence')->default(1);
                $table->string('status', 30)->default('queued');
                $table->string('priority', 20)->default('normal');
                $table->string('service_channel', 30)->default('dine_in');
                $table->dateTime('fired_at')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('ready_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->unsignedInteger('last_changed_by')->nullable();
                $table->unsignedInteger('lock_version')->default(1);
                $table->text('notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->timestamps();
                $table->unique(['transaction_id', 'station_id', 'sequence'], 'rest_kot_transaction_station_unique');
                $table->index(['business_id', 'location_id', 'status'], 'rest_kot_board_index');
            });
        }

        if (! Schema::hasTable('restaurant_kitchen_ticket_items')) {
            Schema::create('restaurant_kitchen_ticket_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kitchen_ticket_id')->index();
                $table->unsignedInteger('transaction_sell_line_id')->index();
                $table->unsignedInteger('product_id')->index();
                $table->unsignedInteger('variation_id')->nullable()->index();
                $table->decimal('quantity', 22, 4);
                $table->string('item_name');
                $table->json('modifier_snapshot')->nullable();
                $table->string('status', 30)->default('queued');
                $table->string('course', 30)->nullable();
                $table->unsignedInteger('guest_number')->nullable();
                $table->timestamps();
                $table->unique(['kitchen_ticket_id', 'transaction_sell_line_id'], 'rest_kot_item_unique');
            });
        }
    }

    private function createRecipesAndInventoryLedger(): void
    {
        if (! Schema::hasTable('restaurant_recipes')) {
            Schema::create('restaurant_recipes', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('menu_product_id')->index();
                $table->unsignedInteger('menu_variation_id')->nullable()->index();
                $table->string('name');
                $table->decimal('yield_quantity', 22, 4)->default(1);
                $table->unsignedInteger('yield_unit_id')->nullable();
                $table->decimal('estimated_cost', 22, 4)->default(0);
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('created_by');
                $table->timestamps();
                $table->unique(['business_id', 'menu_product_id', 'menu_variation_id'], 'rest_recipe_menu_unique');
            });
        }

        if (! Schema::hasTable('restaurant_recipe_lines')) {
            Schema::create('restaurant_recipe_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recipe_id')->index();
                $table->unsignedInteger('ingredient_product_id')->index();
                $table->unsignedInteger('ingredient_variation_id')->index();
                $table->decimal('quantity', 22, 4);
                $table->unsignedInteger('unit_id')->nullable();
                $table->decimal('waste_percentage', 8, 4)->default(0);
                $table->decimal('unit_cost_snapshot', 22, 4)->default(0);
                $table->timestamps();
                $table->unique(['recipe_id', 'ingredient_variation_id'], 'rest_recipe_ingredient_unique');
            });
        }

        if (! Schema::hasTable('restaurant_ingredient_movements')) {
            Schema::create('restaurant_ingredient_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('ingredient_product_id')->index();
                $table->unsignedInteger('ingredient_variation_id')->index();
                $table->unsignedBigInteger('recipe_id')->nullable();
                $table->unsignedInteger('transaction_id')->nullable()->index();
                $table->unsignedInteger('transaction_sell_line_id')->nullable()->index();
                $table->string('movement_type', 30);
                $table->decimal('quantity', 22, 4)->comment('Signed quantity: consumption/waste negative, reversal/production positive.');
                $table->decimal('unit_cost', 22, 4)->default(0);
                $table->decimal('total_cost', 22, 4)->default(0);
                $table->string('idempotency_key', 191)->unique();
                $table->text('reason')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'location_id', 'movement_type'], 'rest_ingredient_movement_index');
            });
        }
    }

    private function createFulfilmentAndReservations(): void
    {
        if (! Schema::hasTable('restaurant_order_fulfilments')) {
            Schema::create('restaurant_order_fulfilments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('transaction_id')->unique();
                $table->string('service_channel', 30)->default('dine_in');
                $table->string('status', 30)->default('received');
                $table->unsignedInteger('booking_id')->nullable()->index();
                $table->unsignedInteger('table_id')->nullable()->index();
                $table->unsignedInteger('guest_count')->nullable();
                $table->string('source_type', 50)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('room_reference', 100)->nullable();
                $table->string('collection_code', 30)->nullable();
                $table->dateTime('promised_at')->nullable();
                $table->dateTime('confirmed_at')->nullable();
                $table->dateTime('ready_at')->nullable();
                $table->dateTime('dispatched_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->json('delivery_address')->nullable();
                $table->text('instructions')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'location_id', 'service_channel', 'status'], 'rest_fulfilment_board_index');
            });
        }

        if (! Schema::hasTable('restaurant_order_events')) {
            Schema::create('restaurant_order_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('transaction_id')->index();
                $table->string('event_type', 60);
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30)->nullable();
                $table->json('payload')->nullable();
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('actor_type', 30)->default('user');
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index(['business_id', 'transaction_id', 'created_at'], 'rest_order_audit_index');
            });
        }

        if (! Schema::hasTable('restaurant_booking_details')) {
            Schema::create('restaurant_booking_details', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('booking_id')->unique();
                $table->uuid('public_reference')->unique();
                $table->unsignedInteger('party_size')->default(1);
                $table->string('source', 30)->default('staff');
                $table->string('status', 30)->default('confirmed');
                $table->dateTime('hold_until')->nullable();
                $table->decimal('expected_spend', 22, 4)->default(0);
                $table->decimal('payment_deposit_amount', 22, 4)->default(0);
                $table->text('special_requests')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->dateTime('arrived_at')->nullable();
                $table->dateTime('seated_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'location_id', 'status'], 'rest_booking_lifecycle_index');
            });
        }

        if (! Schema::hasTable('restaurant_waiter_requests')) {
            Schema::create('restaurant_waiter_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_reference')->unique();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('table_id')->index();
                $table->unsignedInteger('transaction_id')->nullable()->index();
                $table->string('request_type', 30);
                $table->string('status', 20)->default('open');
                $table->text('notes')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->unsignedInteger('acknowledged_by')->nullable();
                $table->dateTime('acknowledged_at')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'location_id', 'status', 'created_at'], 'rest_waiter_request_queue_index');
            });
        }
    }

    private function createRegisterControl(): void
    {
        if (! Schema::hasTable('restaurant_register_movements')) {
            Schema::create('restaurant_register_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('cash_register_id')->index();
                $table->string('movement_type', 30);
                $table->decimal('amount', 22, 4);
                $table->string('reference', 100)->nullable();
                $table->text('reason');
                $table->unsignedInteger('created_by');
                $table->timestamps();
                $table->index(['business_id', 'cash_register_id', 'created_at'], 'rest_register_movement_index');
            });
        }

        if (! Schema::hasTable('restaurant_register_reconciliations')) {
            Schema::create('restaurant_register_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('location_id')->index();
                $table->unsignedInteger('cash_register_id')->unique();
                $table->decimal('expected_cash', 22, 4)->default(0);
                $table->decimal('counted_cash', 22, 4)->default(0);
                $table->decimal('variance', 22, 4)->default(0);
                $table->string('status', 30)->default('draft');
                $table->text('variance_reason')->nullable();
                $table->unsignedInteger('submitted_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'location_id', 'status'], 'rest_register_review_index');
            });
        }

        if (! Schema::hasTable('restaurant_register_counts')) {
            Schema::create('restaurant_register_counts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('reconciliation_id')->index();
                $table->decimal('denomination', 22, 4);
                $table->unsignedInteger('quantity')->default(0);
                $table->decimal('amount', 22, 4)->default(0);
                $table->timestamps();
                $table->unique(['reconciliation_id', 'denomination'], 'rest_register_count_unique');
            });
        }
    }

    private function provisionAccess(): void
    {
        if (Schema::hasTable('permissions')) {
            foreach ((array) config('restaurant_operations.permissions', []) as $name => $label) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (! Schema::hasTable('industries') || ! Schema::hasTable('features') || ! Schema::hasTable('industry_features')) {
            return;
        }

        $featureId = DB::table('features')->where('code', 'restaurant_operations')->value('id');
        if (! $featureId) {
            return;
        }

        $industryIds = DB::table('industries')
            ->whereIn('code', ['restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant'])
            ->pluck('id');

        foreach ($industryIds as $industryId) {
            DB::table('industry_features')->updateOrInsert(
                ['industry_id' => $industryId, 'feature_id' => $featureId],
                ['enabled_by_default' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (! Schema::hasTable('business_features') || ! Schema::hasColumn('business', 'industry_id')) {
            return;
        }

        DB::table('business')
            ->whereIn('industry_id', $industryIds)
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($businesses) use ($featureId) {
                foreach ($businesses as $business) {
                    $existing = DB::table('business_features')
                        ->where('business_id', $business->id)
                        ->where('feature_id', $featureId)
                        ->first();

                    // A company administrator's explicit override always wins.
                    if ($existing && $existing->source !== 'industry_default') {
                        continue;
                    }

                    DB::table('business_features')->updateOrInsert(
                        ['business_id' => $business->id, 'feature_id' => $featureId],
                        ['is_enabled' => true, 'source' => 'industry_default', 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            });
    }

    public function down()
    {
        foreach ([
            'restaurant_register_counts',
            'restaurant_register_reconciliations',
            'restaurant_register_movements',
            'restaurant_waiter_requests',
            'restaurant_booking_details',
            'restaurant_order_events',
            'restaurant_order_fulfilments',
            'restaurant_ingredient_movements',
            'restaurant_recipe_lines',
            'restaurant_recipes',
            'restaurant_kitchen_ticket_items',
            'restaurant_kitchen_tickets',
            'restaurant_product_stations',
            'restaurant_kitchen_stations',
            'restaurant_operation_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
