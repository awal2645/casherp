<?php

namespace Database\Seeders;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DemoLoginsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('business')) {
            return;
        }

        $password = Hash::make('123456');
        $now = Carbon::now();
        $currencyId = null;
        if (Schema::hasTable('currencies')) {
            $currencyId = DB::table('currencies')->where('code', 'USD')->value('id')
                ?: DB::table('currencies')->value('id');
        }

        $shopId = DB::table('business')->orderBy('id')->value('id');
        if (! $shopId) {
            $shopId = DB::table('business')->insertGetId($this->filterColumns('business', [
                'name' => 'CashERP Demo',
                'currency_id' => $currencyId,
                'time_zone' => 'Asia/Dhaka',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->ensureBusinessLocation($shopId, DB::table('business')->where('id', $shopId)->value('name') ?: 'Main');

        $namedBusinesses = [
            'pharmacy' => 'Awesome Pharmacy',
            'electronics' => 'Ultimate Electronics',
            'services' => 'Awesome Services',
            'restaurant' => 'Awesome Restaurant',
            'manufacturers' => 'Manufacturers Demo',
        ];

        $businessIds = ['shop' => $shopId];
        foreach ($namedBusinesses as $key => $name) {
            $id = DB::table('business')->where('name', $name)->value('id');
            if (! $id) {
                $id = DB::table('business')->insertGetId($this->filterColumns('business', [
                    'name' => $name,
                    'currency_id' => $currencyId,
                    'time_zone' => 'Asia/Dhaka',
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
            $this->ensureBusinessLocation($id, $name);
            $businessIds[$key] = $id;
        }

        $users = [
            ['username' => 'admin', 'surname' => 'Mr', 'first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@email.com', 'business' => 'shop'],
            ['username' => 'admin@email.com', 'surname' => 'Mr', 'first_name' => 'Admin', 'last_name' => 'User', 'email' => 'admin@email.com', 'business' => 'shop'],
            ['username' => 'cashier', 'surname' => 'Mr', 'first_name' => 'Demo', 'last_name' => 'Cashier', 'email' => 'cashier@example.com', 'business' => 'shop'],
            ['username' => 'demo-admin', 'surname' => 'Mr.', 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => 'demoadmin@example.com', 'business' => 'shop'],
            ['username' => 'superadmin', 'surname' => 'Mr.', 'first_name' => 'Super', 'last_name' => 'Admin', 'email' => 'superadmin@example.com', 'business' => 'shop'],
            ['username' => 'woocommerce_user', 'surname' => 'Mr.', 'first_name' => 'WooCommerce', 'last_name' => 'User', 'email' => 'woo@example.com', 'business' => 'shop'],
            ['username' => 'admin-essentials', 'surname' => 'Mr', 'first_name' => 'Admin Essential', 'last_name' => null, 'email' => 'admin_essentials@example.com', 'business' => 'shop'],
            ['username' => 'admin-pharmacy', 'surname' => 'Mr', 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => 'admin-pharma@example.com', 'business' => 'pharmacy'],
            ['username' => 'admin-electronics', 'surname' => 'Mr', 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => 'admin-electronics@example.com', 'business' => 'electronics'],
            ['username' => 'admin-services', 'surname' => 'Mr', 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => 'admin-services@example.com', 'business' => 'services'],
            ['username' => 'admin-restaurant', 'surname' => 'Mr', 'first_name' => 'Demo', 'last_name' => 'Admin', 'email' => 'admin-restaurant@example.com', 'business' => 'restaurant'],
            ['username' => 'kevin-nicols', 'surname' => 'Mr', 'first_name' => 'Kevin', 'last_name' => 'Nicols', 'email' => 'kevin@example.com', 'business' => 'restaurant'],
            ['username' => 'manufacturer-demo', 'surname' => 'Mr.', 'first_name' => 'mike', 'last_name' => 'lee', 'email' => 'manufacturer-demo@demo.com', 'business' => 'manufacturers'],
        ];

        foreach ($users as $user) {
            try {
                $businessId = $businessIds[$user['business']] ?? $shopId;
                $existingId = DB::table('users')->where('username', $user['username'])->value('id');

                $loginFields = [
                    'password' => $password,
                    'allow_login' => 1,
                    'status' => 'active',
                    'user_type' => 'user',
                    'business_id' => $businessId,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ];

                if ($existingId) {
                    DB::table('users')->where('id', $existingId)->update($this->filterColumns('users', $loginFields));
                    $this->attachDemoAdminRole((int) $existingId, (int) $businessId);
                    continue;
                }

                $newId = DB::table('users')->insertGetId($this->filterColumns('users', [
                    'surname' => $user['surname'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'password' => $password,
                    'language' => 'en',
                    'business_id' => $businessId,
                    'is_cmmsn_agnt' => 0,
                    'cmmsn_percent' => 0,
                    'user_type' => 'user',
                    'allow_login' => 1,
                    'status' => 'active',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $this->attachDemoAdminRole((int) $newId, (int) $businessId);
            } catch (\Throwable $e) {
                $this->command?->warn('Skipped demo user '.$user['username'].': '.$e->getMessage());
            }
        }

        foreach ($businessIds as $id) {
            $ownerId = DB::table('users')->where('business_id', $id)->orderBy('id')->value('id');
            $businessUpdate = [];
            if ($ownerId && Schema::hasColumn('business', 'owner_id')) {
                $businessUpdate['owner_id'] = $ownerId;
            }
            if (Schema::hasColumn('business', 'is_active')) {
                $businessUpdate['is_active'] = 1;
            }
            if ($businessUpdate !== []) {
                DB::table('business')->where('id', $id)->update($businessUpdate);
            }
        }

        $this->command?->info('Demo logins ready. Password for all accounts: 123456');
    }

    private function ensureBusinessLocation(int $businessId, string $name): void
    {
        if (! Schema::hasTable('business_locations')) {
            return;
        }

        $exists = DB::table('business_locations')->where('business_id', $businessId)->exists();
        if ($exists) {
            return;
        }

        DB::table('business_locations')->insert($this->filterColumns('business_locations', [
            'business_id' => $businessId,
            'name' => $name,
            'landmark' => 'Linking Street',
            'country' => 'Bangladesh',
            'state' => 'Dhaka',
            'city' => 'Dhaka',
            'zip_code' => '1205',
            'is_active' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]));
    }

    private function filterColumns(string $table, array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (Schema::hasColumn($table, $key)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    private function attachDemoAdminRole(int $userId, int $businessId): void
    {
        try {
            $userQuery = User::query();
            if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(User::class), true)) {
                $userQuery->withTrashed();
            }
            $user = $userQuery->find($userId);
            if (! $user || ! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
                return;
            }

            if ($user->deleted_at) {
                $user->restore();
            }

            $permissionNames = [
                'dashboard.data',
                'sell.view',
                'sell.create',
                'sell.update',
                'sell.delete',
                'access_all_locations',
            ];
            $now = now();
            foreach ($permissionNames as $name) {
                if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                    DB::table('permissions')->insert($this->filterColumns('permissions', [
                        'name' => $name,
                        'guard_name' => 'web',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));
                }
            }

            $roleName = 'Admin#'.$businessId;
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                $this->filterColumns('roles', [
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'business_id' => $businessId,
                    'is_default' => 1,
                ])
            );

            $permissions = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $permissionNames)
                ->get();
            if ($permissions->isNotEmpty()) {
                $role->syncPermissions($permissions);
            }

            if (! $user->hasRole($role->name)) {
                $user->assignRole($role->name);
            }

            if (class_exists(\Spatie\Permission\PermissionRegistrar::class)) {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            }
        } catch (\Throwable $e) {
            $this->command?->warn('Could not attach demo role for user #'.$userId.': '.$e->getMessage());
        }
    }
}
