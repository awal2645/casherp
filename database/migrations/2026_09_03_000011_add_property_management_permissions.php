<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'property.view',
        'property.manage',
        'property.rent.manage',
        'property.maintenance.manage',
        'property.accounting.manage',
    ];

    public function up(): void
    {
        $permissionTable = config('permission.table_names.permissions');
        if (! Schema::hasTable($permissionTable)) {
            return;
        }

        $now = now();
        foreach ($this->permissions as $permission) {
            DB::table($permissionTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $permissionIds = DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', $this->permissions)
            ->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table(config('permission.table_names.model_has_permissions'))
            ->whereIn('permission_id', $permissionIds)
            ->delete();
        DB::table(config('permission.table_names.permissions'))
            ->whereIn('id', $permissionIds)
            ->delete();
        Cache::forget(config('permission.cache.key'));
    }
};
