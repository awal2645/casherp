<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('property_access_grants')) {
            Schema::create('property_access_grants', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->string('subject_type', 20);
                $table->unsignedBigInteger('subject_id');
                $table->json('abilities');
                $table->unsignedInteger('created_by');
                $table->timestamps();
                $table->unique(['business_id', 'property_id', 'subject_type', 'subject_id'], 'property_access_subject_unique');
                $table->index(['business_id', 'subject_type', 'subject_id'], 'property_access_subject_index');
            });
        }

        $permissionTable = config('permission.table_names.permissions');
        foreach (['property.access.manage', 'sell.document.share', 'purchase.document.share'] as $permission) {
            DB::table($permissionTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        $adminRoleIds = DB::table(config('permission.table_names.roles'))
            ->where('name', 'like', 'Admin#%')
            ->pluck('id');
        $shareRoleIds = DB::table(config('permission.table_names.role_has_permissions').' as rhp')
            ->join($permissionTable.' as permissions', 'permissions.id', '=', 'rhp.permission_id')
            ->whereIn('permissions.name', ['sell.create', 'direct_sell.access'])
            ->pluck('rhp.role_id');
        $purchaseShareRoleIds = DB::table(config('permission.table_names.role_has_permissions').' as rhp')
            ->join($permissionTable.' as permissions', 'permissions.id', '=', 'rhp.permission_id')
            ->whereIn('permissions.name', ['purchase_order.create', 'purchase_order.view_all'])
            ->pluck('rhp.role_id');
        $permissionIds = DB::table($permissionTable)
            ->whereIn('name', ['property.access.manage', 'sell.document.share', 'purchase.document.share'])
            ->pluck('id', 'name');

        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
        if ($permissionIds->has('sell.document.share')) {
            foreach ($shareRoleIds as $roleId) {
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore([
                    'permission_id' => $permissionIds['sell.document.share'],
                    'role_id' => $roleId,
                ]);
            }
        }
        if ($permissionIds->has('purchase.document.share')) {
            foreach ($purchaseShareRoleIds as $roleId) {
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore([
                    'permission_id' => $permissionIds['purchase.document.share'],
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down()
    {
        $permissionTable = config('permission.table_names.permissions');
        $permissionIds = DB::table($permissionTable)
            ->whereIn('name', ['property.access.manage', 'sell.document.share', 'purchase.document.share'])
            ->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table($permissionTable)->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('property_access_grants');
    }
};
