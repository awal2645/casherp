<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_types')) {
            return;
        }

        $now = now();
        $codes = [
            'room_booking_quotation',
            'room_booking_invoice',
            'booking_payment_receipt',
            'event_quotation',
            'event_booking_invoice',
            'event_payment_receipt',
            'pos_receipt',
            'standard_invoice',
        ];
        $nextSort = ((int) DB::table('document_types')->max('sort_order')) + 10;

        foreach ($codes as $code) {
            $definition = (array) config('smart_documents.types.'.$code, []);
            if (! $definition) {
                continue;
            }
            $typeId = DB::table('document_types')->where('code', $code)->value('id');
            $values = [
                'name' => $definition['name'],
                'default_title' => $definition['title'],
                'category' => $definition['category'],
                'default_prefix' => $definition['prefix'],
                'scenario_code' => $definition['scenario'],
                'schema_key' => $definition['schema'] ?? null,
                'convert_to_code' => $definition['convert_to'] ?? null,
                'output_format' => $definition['output_format'] ?? 'a4',
                'supports_line_items' => $definition['supports_lines'] ?? true,
                'is_financial' => $definition['is_financial'] ?? false,
                'requires_acceptance' => $definition['requires_acceptance'] ?? false,
                'is_active' => true,
                'updated_at' => $now,
            ];
            if ($typeId) {
                DB::table('document_types')->where('id', $typeId)->update($values);
            } else {
                $typeId = DB::table('document_types')->insertGetId($values + [
                    'code' => $code,
                    'sort_order' => $nextSort,
                    'created_at' => $now,
                ]);
                $nextSort += 10;
            }

            if ($code !== 'event_payment_receipt') {
                continue;
            }

            $industryIds = Schema::hasTable('industries')
                ? DB::table('industries')->whereIn('code', [
                    'restaurant_food_service',
                    'hotel_lodge_guesthouse',
                    'hotel_with_restaurant',
                    'property_management_rentals',
                ])->pluck('id')
                : collect();
            if (Schema::hasTable('industry_document_types')) {
                foreach ($industryIds as $industryId) {
                    DB::table('industry_document_types')->updateOrInsert(
                        ['industry_id' => $industryId, 'document_type_id' => $typeId],
                        ['enabled_by_default' => true, 'sort_order' => $nextSort, 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
            if (Schema::hasTable('business_document_settings') && Schema::hasTable('business')) {
                foreach (DB::table('business')->whereIn('industry_id', $industryIds)->pluck('id') as $businessId) {
                    DB::table('business_document_settings')->updateOrInsert(
                        ['business_id' => $businessId, 'document_type_id' => $typeId],
                        ['is_enabled' => true, 'source' => 'industry_default', 'prefix' => $definition['prefix'], 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
            $this->installPermission($code, $now);
        }

        Cache::forget(config('permission.cache.key'));
    }

    private function installPermission(string $code, $now): void
    {
        $permissionTable = config('permission.table_names.permissions', 'permissions');
        $pivot = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        if (! Schema::hasTable($permissionTable) || ! Schema::hasTable($pivot) || ! Schema::hasTable('roles')) {
            return;
        }
        $name = 'smart_documents.type.'.$code;
        DB::table($permissionTable)->updateOrInsert(
            ['name' => $name, 'guard_name' => 'web'],
            ['created_at' => $now, 'updated_at' => $now]
        );
        $permissionId = DB::table($permissionTable)->where('name', $name)->where('guard_name', 'web')->value('id');
        $roleIds = DB::table('roles')->where('name', 'like', 'Admin#%')->pluck('id');
        $sourcePermissionIds = DB::table($permissionTable)
            ->whereIn('name', ['hms.manage_events', 'smart_documents.type.event_booking_invoice'])
            ->pluck('id');
        if ($sourcePermissionIds->isNotEmpty()) {
            $roleIds = $roleIds->merge(DB::table($pivot)->whereIn('permission_id', $sourcePermissionIds)->pluck('role_id'));
        }
        foreach ($roleIds->unique() as $roleId) {
            DB::table($pivot)->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_types')) {
            return;
        }
        $type = DB::table('document_types')->where('code', 'event_payment_receipt')->first();
        if ($type) {
            $hasDocuments = Schema::hasTable('business_documents')
                && DB::table('business_documents')->where('document_type_id', $type->id)->exists();
            if ($hasDocuments) {
                DB::table('document_types')->where('id', $type->id)->update(['is_active' => false, 'updated_at' => now()]);
            } else {
                if (Schema::hasTable('business_document_settings')) {
                    DB::table('business_document_settings')->where('document_type_id', $type->id)->delete();
                }
                if (Schema::hasTable('industry_document_types')) {
                    DB::table('industry_document_types')->where('document_type_id', $type->id)->delete();
                }
                DB::table('document_types')->where('id', $type->id)->delete();
            }
        }
        Cache::forget(config('permission.cache.key'));
    }
};
