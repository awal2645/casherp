<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('premium_module_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('module_price', 22, 4)->default(0);
            $table->enum('billing_interval', ['month', 'year', 'one_time'])->default('month');
            $table->unsignedInteger('billing_interval_count')->default(1);
            $table->string('allowance_name', 100)->default('uses');
            $table->unsignedBigInteger('included_allowance')->default(0)
                ->comment('0 means unlimited while the module entitlement is active.');
            $table->enum('allowance_reset', ['monthly', 'subscription', 'never'])->default('monthly');
            $table->unsignedBigInteger('capacity_increment')->default(0);
            $table->decimal('capacity_price', 22, 4)->default(0);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('package_premium_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('package_id');
            $table->foreignId('premium_module_plan_id')->constrained('premium_module_plans')->cascadeOnDelete();
            $table->unsignedBigInteger('included_allowance_override')->nullable();
            $table->timestamps();
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
            $table->unique(['package_id', 'premium_module_plan_id'], 'package_premium_module_unique');
        });

        Schema::create('business_module_entitlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('premium_module_plan_id')->constrained('premium_module_plans')->cascadeOnDelete();
            $table->unsignedInteger('subscription_id')->nullable()->index();
            $table->enum('source', ['package', 'module_purchase', 'capacity_purchase', 'manual'])->default('module_purchase');
            $table->unsignedBigInteger('base_allowance')->default(0);
            $table->unsignedBigInteger('extra_allowance')->default(0);
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->decimal('amount_paid', 22, 4)->default(0);
            $table->string('payment_reference')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->index(['business_id', 'premium_module_plan_id', 'status'], 'business_module_entitlement_lookup');
        });

        Schema::create('business_module_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->foreignId('premium_module_plan_id')->constrained('premium_module_plans')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end')->nullable();
            $table->unsignedBigInteger('used_quantity')->default(0);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->unique(
                ['business_id', 'premium_module_plan_id', 'period_start'],
                'business_module_usage_period_unique'
            );
        });

        Schema::create('business_module_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->foreignId('premium_module_plan_id')->constrained('premium_module_plans')->cascadeOnDelete();
            $table->unsignedInteger('requested_by');
            $table->enum('order_type', ['module', 'capacity'])->default('module');
            $table->unsignedInteger('increments')->default(1);
            $table->unsignedBigInteger('allowance_quantity')->default(0);
            $table->decimal('unit_price', 22, 4)->default(0);
            $table->decimal('total_price', 22, 4)->default(0);
            $table->string('currency', 10);
            $table->enum('status', ['pending', 'approved', 'declined', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'status']);
        });

        Schema::create('business_closure_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('requested_by');
            $table->enum('status', ['scheduled', 'cancelled', 'closed'])->default('scheduled');
            $table->text('reason')->nullable();
            $table->timestamp('scheduled_for');
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->json('company_snapshot')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'scheduled_for']);
        });

        $defaults = [
            'hrm' => ['hrm', 'Human Resource Management', 'Active employees', 50, 'never', 50, 10],
            'hms' => ['hms', 'Hotel Management', 'Rooms', 50, 'never', 25, 20],
            'property_management' => ['property_management', 'Property Management', 'Units', 100, 'never', 50, 30],
            'smart_documents' => ['smart_documents', 'Smart Documents', 'Documents per month', 1000, 'monthly', 1000, 40],
        ];
        $now = now();
        foreach ($defaults as $featureCode => $definition) {
            $featureId = DB::table('features')->where('code', $featureCode)->value('id');
            if ($featureId) {
                DB::table('premium_module_plans')->insert([
                    'feature_id' => $featureId,
                    'code' => $definition[0],
                    'name' => $definition[1],
                    'description' => 'Optional capacity and usage controls managed by the CashERP Super Admin.',
                    'module_price' => 0,
                    'billing_interval' => 'month',
                    'billing_interval_count' => 1,
                    'allowance_name' => $definition[2],
                    'included_allowance' => $definition[3],
                    'allowance_reset' => $definition[4],
                    'capacity_increment' => $definition[5],
                    'capacity_price' => 0,
                    'is_active' => false,
                    'sort_order' => $definition[6],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('permissions')->updateOrInsert(
            ['name' => 'business.account_closure.manage', 'guard_name' => 'web'],
            ['updated_at' => $now, 'created_at' => $now]
        );
        DB::table('permissions')->updateOrInsert(
            ['name' => 'premium_modules.purchase', 'guard_name' => 'web'],
            ['updated_at' => $now, 'created_at' => $now]
        );
        Cache::forget(config('permission.cache.key'));
    }

    public function down()
    {
        Cache::forget(config('permission.cache.key'));
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', ['business.account_closure.manage', 'premium_modules.purchase'])
                ->pluck('id');
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::dropIfExists('business_closure_requests');
        Schema::dropIfExists('business_module_orders');
        Schema::dropIfExists('business_module_usage');
        Schema::dropIfExists('business_module_entitlements');
        Schema::dropIfExists('package_premium_modules');
        Schema::dropIfExists('premium_module_plans');
    }
};
