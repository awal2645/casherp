<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->text('description')->nullable()->after('address');
            $table->json('amenities')->nullable()->after('description');
        });

        Schema::table('property_units', function (Blueprint $table) {
            $table->string('listing_purpose', 20)->default('rent')->after('unit_type');
            $table->decimal('asking_price', 22, 4)->nullable()->after('monthly_rent');
            $table->date('available_from')->nullable()->after('asking_price');
            $table->boolean('is_listed')->default(false)->after('available_from');
            $table->string('listing_status', 20)->default('draft')->after('is_listed');
            $table->index(['listing_purpose', 'listing_status', 'available_from'], 'property_units_listing_index');
        });

        Schema::create('property_viewing_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id');
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('property_unit_id')->nullable()->constrained('property_units')->nullOnDelete();
            $table->unsignedInteger('contact_id')->nullable();
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('requester_phone', 60)->nullable();
            $table->dateTime('requested_start_at');
            $table->dateTime('requested_end_at');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->unsignedInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('source', 30)->default('staff');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'requested_start_at'], 'property_viewings_schedule_index');
        });

        $permissionTable = config('permission.table_names.permissions');
        DB::table($permissionTable)->updateOrInsert(
            ['name' => 'property.viewings.manage', 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()]
        );
        $permissionId = DB::table($permissionTable)->where('name', 'property.viewings.manage')->where('guard_name', 'web')->value('id');
        $roleIds = DB::table(config('permission.table_names.roles'))->where('guard_name', 'web')->where('name', 'like', 'Admin#%')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $permissionTable = config('permission.table_names.permissions');
        $permissionIds = DB::table($permissionTable)->where('name', 'property.viewings.manage')->where('guard_name', 'web')->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table($permissionTable)->whereIn('id', $permissionIds)->delete();
        Cache::forget(config('permission.cache.key'));

        Schema::dropIfExists('property_viewing_requests');
        Schema::table('property_units', function (Blueprint $table) {
            $table->dropIndex('property_units_listing_index');
            $table->dropColumn(['listing_purpose', 'asking_price', 'available_from', 'is_listed', 'listing_status']);
        });
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['description', 'amenities']);
        });
    }
};
