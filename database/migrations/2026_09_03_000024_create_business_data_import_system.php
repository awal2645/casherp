<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'data_import.view',
        'data_import.manage',
        'data_import.approve',
        'data_import.rollback',
    ];

    public function up(): void
    {
        Schema::create('business_data_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('business_location_id')->nullable();
            $table->unsignedInteger('uploaded_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->string('dataset', 80);
            $table->string('industry_code', 80)->nullable();
            $table->string('original_name');
            $table->string('storage_disk', 40)->default('local');
            $table->string('storage_path');
            $table->char('checksum', 64);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 40)->default('uploaded');
            $table->string('duplicate_strategy', 20)->default('reject');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('metadata')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('business_location_id')->references('id')->on('business_locations')->nullOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'created_at'], 'business_import_status_index');
            $table->index(['business_id', 'dataset', 'checksum'], 'business_import_checksum_index');
        });

        Schema::create('business_data_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_data_import_id')->constrained('business_data_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_values');
            $table->json('normalized_values')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('previous_values')->nullable();
            $table->char('fingerprint', 64)->nullable();
            $table->string('status', 30)->default('staged');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->unique(['business_data_import_id', 'row_number'], 'business_import_row_unique');
            $table->index(['business_data_import_id', 'status'], 'business_import_row_status_index');
            $table->index(['target_type', 'target_id'], 'business_import_row_target_index');
        });

        Schema::create('business_data_import_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_data_import_id')->constrained('business_data_imports')->cascadeOnDelete();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 60);
            $table->json('context')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'event', 'created_at'], 'business_import_event_index');
        });

        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'data_import_enabled')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->boolean('data_import_enabled')->default(true);
                $table->unsignedInteger('monthly_import_rows')->default(50000);
                $table->unsignedInteger('max_import_rows_per_file')->default(10000);
                $table->unsignedSmallInteger('max_import_file_size_mb')->default(10);
                $table->unsignedSmallInteger('concurrent_imports')->default(2);
                $table->unsignedSmallInteger('import_rollback_days')->default(7);
            });
        }

        $now = now();
        $permissionTable = config('permission.table_names.permissions');
        foreach ($this->permissions as $permission) {
            DB::table($permissionTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissionIds = DB::table($permissionTable)
            ->whereIn('name', $this->permissions)
            ->pluck('id');
        $roleTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.role_has_permissions');
        $adminRoleIds = DB::table($roleTable)
            ->where('guard_name', 'web')
            ->where('name', 'like', 'Admin#%')
            ->pluck('id');
        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table($pivotTable)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $permissionTable = config('permission.table_names.permissions');
        $permissionIds = DB::table($permissionTable)
            ->whereIn('name', $this->permissions)
            ->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table($permissionTable)->whereIn('id', $permissionIds)->delete();
        Cache::forget(config('permission.cache.key'));

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn([
                'data_import_enabled',
                'monthly_import_rows',
                'max_import_rows_per_file',
                'max_import_file_size_mb',
                'concurrent_imports',
                'import_rollback_days',
            ]);
        });

        Schema::dropIfExists('business_data_import_events');
        Schema::dropIfExists('business_data_import_rows');
        Schema::dropIfExists('business_data_imports');
    }
};
