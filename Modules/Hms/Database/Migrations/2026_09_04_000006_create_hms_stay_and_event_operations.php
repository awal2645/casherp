<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('hms_booking_guests')) {
            Schema::create('hms_booking_guests', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('transaction_id')->index();
                $table->unsignedBigInteger('hms_booking_line_id')->nullable()->index();
                $table->unsignedInteger('contact_id')->nullable()->index();
                $table->unsignedBigInteger('hms_guest_profile_id')->nullable()->index();
                $table->string('full_name');
                $table->string('guest_type', 24)->default('adult');
                $table->boolean('is_primary')->default(false);
                $table->string('nationality', 80)->nullable();
                $table->string('identity_document_type', 40)->nullable();
                $table->string('identity_document_last_four', 8)->nullable();
                $table->dateTime('checked_in_at')->nullable();
                $table->dateTime('checked_out_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'transaction_id', 'is_primary'], 'hms_stay_guest_booking_idx');
            });
        }

        if (! Schema::hasTable('hms_room_moves')) {
            Schema::create('hms_room_moves', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('transaction_id')->index();
                $table->unsignedBigInteger('hms_booking_line_id')->index();
                $table->unsignedBigInteger('from_room_id')->index();
                $table->unsignedBigInteger('to_room_id')->index();
                $table->dateTime('moved_at');
                $table->text('reason');
                $table->text('notes')->nullable();
                $table->unsignedInteger('moved_by');
                $table->timestamps();
                $table->index(['business_id', 'moved_at'], 'hms_room_move_audit_idx');
            });
        }

        if (! Schema::hasTable('hms_event_bookings')) {
            Schema::create('hms_event_bookings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('hms_property_id')->index();
                $table->unsignedBigInteger('hms_event_venue_id')->index();
                $table->unsignedInteger('location_id')->nullable()->index();
                $table->unsignedInteger('contact_id')->nullable()->index();
                $table->string('event_number', 80);
                $table->string('event_name');
                $table->string('event_type', 40)->default('meeting');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedInteger('expected_guests')->default(1);
                $table->unsignedInteger('guaranteed_guests')->nullable();
                $table->string('status', 24)->default('tentative');
                $table->decimal('agreed_amount', 22, 4)->default(0);
                $table->decimal('payment_deposit_required', 22, 4)->default(0);
                $table->text('special_requests')->nullable();
                $table->text('internal_notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'event_number'], 'hms_event_booking_number_unique');
                $table->index(['business_id', 'hms_event_venue_id', 'starts_at', 'ends_at'], 'hms_event_availability_idx');
            });
        }

        if (! Schema::hasTable('hms_event_charges')) {
            Schema::create('hms_event_charges', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedBigInteger('hms_event_booking_id')->index();
                $table->unsignedBigInteger('hms_folio_entry_id')->nullable()->index();
                $table->string('category', 40);
                $table->string('description');
                $table->decimal('amount', 22, 4);
                $table->string('direction', 8)->default('debit');
                $table->string('status', 24)->default('posted');
                $table->string('idempotency_key', 160)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'idempotency_key'], 'hms_event_charge_idempotency_unique');
            });
        }

        if (! Schema::hasTable('hms_restaurant_postings')) {
            Schema::create('hms_restaurant_postings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('restaurant_transaction_id')->index();
                $table->string('source_type', 24);
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('hms_folio_id')->index();
                $table->unsignedBigInteger('hms_folio_entry_id')->index();
                $table->decimal('amount', 22, 4);
                $table->string('status', 24)->default('posted');
                $table->string('idempotency_key', 160);
                $table->unsignedInteger('posted_by')->nullable();
                $table->dateTime('posted_at');
                $table->unsignedInteger('voided_by')->nullable();
                $table->dateTime('voided_at')->nullable();
                $table->text('void_reason')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'restaurant_transaction_id'], 'hms_restaurant_sale_unique');
                $table->unique(['business_id', 'idempotency_key'], 'hms_restaurant_post_idem_unique');
                $table->index(['business_id', 'source_type', 'source_id'], 'hms_restaurant_source_idx');
            });
        }

        if (Schema::hasTable('hms_folios') && ! Schema::hasColumn('hms_folios', 'hms_event_booking_id')) {
            Schema::table('hms_folios', function (Blueprint $table) {
                $table->unsignedBigInteger('hms_event_booking_id')->nullable()->after('hms_group_booking_id');
                $table->unique(['business_id', 'hms_event_booking_id'], 'hms_folios_event_unique');
            });
        }

        foreach ([
            'hms.room_status_board',
            'hms.manage_stay_guests',
            'hms.manage_room_moves',
            'hms.manage_events',
            'hms.post_restaurant_charges',
        ] as $permission) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $permission, 'guard_name' => 'web'],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down()
    {
        if (Schema::hasTable('hms_folios') && Schema::hasColumn('hms_folios', 'hms_event_booking_id')) {
            Schema::table('hms_folios', function (Blueprint $table) {
                $table->dropUnique('hms_folios_event_unique');
                $table->dropColumn('hms_event_booking_id');
            });
        }

        foreach (['hms_restaurant_postings', 'hms_event_charges', 'hms_event_bookings', 'hms_room_moves', 'hms_booking_guests'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
