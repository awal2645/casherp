<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class LocalAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('business')) {
            return;
        }

        $currencyId = null;
        if (Schema::hasTable('currencies')) {
            $currencyId = DB::table('currencies')->where('code', 'USD')->value('id')
                ?: DB::table('currencies')->value('id');
        }

        $businessId = DB::table('business')->value('id');
        if (! $businessId) {
            $businessId = DB::table('business')->insertGetId([
                'name' => 'CashERP Demo',
                'currency_id' => $currencyId,
                'time_zone' => 'UTC',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $password = Hash::make('123456');
        $now = now();

        foreach (['admin', 'admin@email.com'] as $username) {
            $payload = [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@email.com',
                'password' => $password,
                'language' => 'en',
                'business_id' => $businessId,
                'user_type' => 'user',
                'allow_login' => 1,
                'status' => 'active',
                'updated_at' => $now,
            ];

            $existingId = DB::table('users')->where('username', $username)->value('id');
            if ($existingId) {
                DB::table('users')->where('id', $existingId)->update($payload);
            } else {
                $payload['username'] = $username;
                $payload['created_at'] = $now;
                DB::table('users')->insert($payload);
            }
        }

        DB::table('business')->where('id', $businessId)->update([
            'owner_id' => DB::table('users')->where('username', 'admin')->value('id')
                ?: DB::table('users')->value('id'),
            'currency_id' => $currencyId,
            'is_active' => 1,
        ]);
    }
}
