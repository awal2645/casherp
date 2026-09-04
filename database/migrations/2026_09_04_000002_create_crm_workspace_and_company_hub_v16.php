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
        $this->createCrmWorkspace();
        $this->createCompanyHub();
        $this->registerFeaturesAndPermissions();
    }

    private function createCrmWorkspace(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('name');
            $table->string('entity_type', 30)->default('opportunity');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['business_id', 'name'], 'crm_pipeline_business_name_unique');
        });

        Schema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('color', 20)->default('#3c8dbc');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->unique(['crm_pipeline_id', 'code'], 'crm_stage_pipeline_code_unique');
            $table->index(['crm_pipeline_id', 'position']);
        });

        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('crm_pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->foreignId('crm_pipeline_stage_id')->constrained('crm_pipeline_stages')->restrictOnDelete();
            $table->unsignedInteger('contact_id')->nullable()->index();
            $table->unsignedInteger('business_location_id')->nullable()->index();
            $table->unsignedInteger('owner_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('estimated_value', 22, 4)->default(0);
            $table->char('currency_code', 3)->nullable();
            $table->date('expected_close_date')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 20)->default('open');
            $table->string('lost_reason')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('next_activity_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('created_by');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('business_location_id')->references('id')->on('business_locations')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['business_id', 'status', 'expected_close_date'], 'crm_opportunity_status_date_index');
            $table->index(['business_id', 'owner_id', 'next_activity_at'], 'crm_opportunity_owner_activity_index');
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();
            $table->unsignedInteger('contact_id')->nullable()->index();
            $table->unsignedInteger('owner_id')->index();
            $table->string('type', 30);
            $table->string('direction', 20)->default('internal');
            $table->string('subject');
            $table->text('details')->nullable();
            $table->string('outcome', 100)->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_by')->nullable();
            $table->unsignedInteger('created_by');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['business_id', 'status', 'due_at'], 'crm_activity_due_index');
            $table->index(['remind_at', 'reminder_sent_at'], 'crm_activity_reminder_index');
        });

        Schema::create('crm_stage_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('crm_opportunity_id')->constrained('crm_opportunities')->cascadeOnDelete();
            $table->unsignedBigInteger('from_stage_id')->nullable();
            $table->unsignedBigInteger('to_stage_id');
            $table->unsignedInteger('moved_by');
            $table->text('note')->nullable();
            $table->timestamp('moved_at');
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('from_stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
            $table->foreign('to_stage_id')->references('id')->on('crm_pipeline_stages')->restrictOnDelete();
            $table->foreign('moved_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['crm_opportunity_id', 'moved_at']);
        });
    }

    private function createCompanyHub(): void
    {
        Schema::create('company_hub_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->unique();
            $table->boolean('comments_enabled')->default(true);
            $table->unsignedInteger('retention_days')->default(1095);
            $table->unsignedInteger('max_attachment_mb')->default(10);
            $table->boolean('email_important_announcements')->default(false);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });

        Schema::create('company_hub_channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('business_location_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('open');
            $table->json('department_ids')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('business_location_id')->references('id')->on('business_locations')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['business_id', 'slug'], 'company_hub_channel_slug_unique');
        });

        Schema::create('company_hub_channel_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('company_hub_channel_id')->constrained('company_hub_channels')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->string('role', 20)->default('member');
            $table->unsignedInteger('added_by');
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('added_by')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['company_hub_channel_id', 'user_id'], 'company_hub_channel_member_unique');
        });

        Schema::create('company_hub_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('company_hub_channel_id')->nullable()->constrained('company_hub_channels')->nullOnDelete();
            $table->unsignedInteger('author_id');
            $table->string('type', 20)->default('discussion');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('priority', 20)->default('normal');
            $table->string('audience_type', 20)->default('company');
            $table->json('audience_location_ids')->nullable();
            $table->json('audience_department_ids')->nullable();
            $table->json('audience_role_ids')->nullable();
            $table->json('audience_user_ids')->nullable();
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('acknowledgement_required')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['business_id', 'published_at', 'expires_at'], 'company_hub_post_visibility_index');
            $table->index(['business_id', 'type', 'priority'], 'company_hub_post_type_index');
        });

        Schema::create('company_hub_post_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('company_hub_post_id')->constrained('company_hub_posts')->cascadeOnDelete();
            $table->unsignedInteger('user_id');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['company_hub_post_id', 'user_id'], 'company_hub_post_user_ack_unique');
        });

        Schema::create('company_hub_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('company_hub_post_id')->constrained('company_hub_posts')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('company_hub_comments')->nullOnDelete();
            $table->unsignedInteger('user_id');
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('company_hub_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->string('type', 20);
            $table->string('category', 100)->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('previous_version_id')->nullable();
            $table->string('audience_type', 20)->default('company');
            $table->json('audience_location_ids')->nullable();
            $table->json('audience_department_ids')->nullable();
            $table->json('audience_role_ids')->nullable();
            $table->json('audience_user_ids')->nullable();
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path', 1000)->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('review_date')->nullable();
            $table->unsignedInteger('owner_id');
            $table->unsignedInteger('created_by');
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('previous_version_id')->references('id')->on('company_hub_resources')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['business_id', 'type', 'status'], 'company_hub_resource_lookup');
        });

        Schema::create('company_hub_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('business_location_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('location_text')->nullable();
            $table->string('audience_type', 20)->default('company');
            $table->json('audience_location_ids')->nullable();
            $table->json('audience_department_ids')->nullable();
            $table->json('audience_role_ids')->nullable();
            $table->json('audience_user_ids')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->unsignedInteger('owner_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('business_location_id')->references('id')->on('business_locations')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['business_id', 'status', 'starts_at'], 'company_hub_event_schedule_index');
        });

        Schema::create('company_hub_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('event', 80);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('created_at');
            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'auditable_type', 'auditable_id'], 'company_hub_audit_lookup');
        });
    }

    private function registerFeaturesAndPermissions(): void
    {
        $now = now();
        DB::table('features')->updateOrInsert(
            ['code' => 'company_hub'],
            ['name' => 'Company Hub & Intranet', 'module_key' => 'company_hub', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );

        $industryCodes = [
            'general_business', 'restaurant_food_service', 'hotel_lodge_guesthouse',
            'hotel_with_restaurant', 'property_management_rentals', 'professional_services',
        ];
        $industryIds = DB::table('industries')->whereIn('code', $industryCodes)->pluck('id');
        $hubFeatureId = DB::table('features')->where('code', 'company_hub')->value('id');
        $crmFeatureId = DB::table('features')->where('code', 'crm')->value('id');

        foreach ($industryIds as $industryId) {
            foreach (array_filter([$hubFeatureId, $crmFeatureId]) as $featureId) {
                DB::table('industry_features')->updateOrInsert(
                    ['industry_id' => $industryId, 'feature_id' => $featureId],
                    ['enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        DB::table('business')->whereIn('industry_id', $industryIds)->orderBy('id')->chunkById(200, function ($businesses) use ($hubFeatureId, $crmFeatureId, $now) {
            foreach ($businesses as $business) {
                foreach (['company_hub' => $hubFeatureId, 'crm' => $crmFeatureId] as $code => $featureId) {
                    if (! $featureId || DB::table('business_features')->where('business_id', $business->id)->where('feature_id', $featureId)->exists()) {
                        continue;
                    }
                    DB::table('business_features')->insert([
                        'business_id' => $business->id,
                        'feature_id' => $featureId,
                        'is_enabled' => true,
                        'source' => 'v16_global_default',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });

        $permissions = [
            'crm.workspace.view', 'crm.pipeline.manage', 'crm.opportunity.manage',
            'crm.activity.manage', 'crm.reports.view',
            'company_hub.view', 'company_hub.post', 'company_hub.comment',
            'company_hub.publish_announcements', 'company_hub.manage_channels',
            'company_hub.manage_knowledge', 'company_hub.manage_documents',
            'company_hub.manage_events', 'company_hub.manage_settings',
            'company_hub.view_acknowledgements', 'company_hub.moderate',
        ];
        foreach ($permissions as $permission) {
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissionIds = DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id');
        $adminRoleIds = DB::table(config('permission.table_names.roles'))
            ->where('guard_name', 'web')->where('name', 'like', 'Admin#%')->pluck('id');
        foreach ($adminRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        if (Schema::hasTable('premium_module_plans')) {
            $plans = [
                [$hubFeatureId, 'company_hub', 'Company Hub & Intranet', 'Published posts per month', 1000, 'monthly', 1000, 55],
                [$crmFeatureId, 'crm_pipeline', 'CRM Pipeline', 'Active opportunities', 500, 'never', 250, 50],
            ];
            foreach ($plans as $plan) {
                if (! $plan[0]) {
                    continue;
                }
                DB::table('premium_module_plans')->updateOrInsert(
                    ['code' => $plan[1]],
                    [
                        'feature_id' => $plan[0], 'name' => $plan[2],
                        'description' => 'Optional CashERP SaaS capacity controlled by the Super Admin.',
                        'module_price' => 0, 'billing_interval' => 'month', 'billing_interval_count' => 1,
                        'allowance_name' => $plan[3], 'included_allowance' => $plan[4],
                        'allowance_reset' => $plan[5], 'capacity_increment' => $plan[6], 'capacity_price' => 0,
                        'is_active' => false, 'sort_order' => $plan[7], 'deleted_at' => null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]
                );
            }
        }

        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Cache::forget(config('permission.cache.key'));
        $permissions = DB::table(config('permission.table_names.permissions'))
            ->where('name', 'like', 'company_hub.%')
            ->orWhereIn('name', ['crm.workspace.view', 'crm.pipeline.manage', 'crm.opportunity.manage', 'crm.activity.manage', 'crm.reports.view'])
            ->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissions)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissions)->delete();
        DB::table(config('permission.table_names.permissions'))->whereIn('id', $permissions)->delete();

        if (Schema::hasTable('premium_module_plans')) {
            DB::table('premium_module_plans')->whereIn('code', ['company_hub', 'crm_pipeline'])->delete();
        }
        $featureIds = DB::table('features')->whereIn('code', ['company_hub', 'crm'])->pluck('id', 'code');
        if ($featureIds->has('company_hub')) {
            DB::table('business_features')->where('feature_id', $featureIds['company_hub'])->delete();
            DB::table('industry_features')->where('feature_id', $featureIds['company_hub'])->delete();
            DB::table('features')->where('id', $featureIds['company_hub'])->delete();
        }
        if ($featureIds->has('crm')) {
            DB::table('business_features')->where('feature_id', $featureIds['crm'])->where('source', 'v16_global_default')->delete();
            $removeIndustryIds = DB::table('industries')->whereIn('code', [
                'general_business',
                'restaurant_food_service',
                'hotel_lodge_guesthouse',
                'hotel_with_restaurant',
            ])->pluck('id');
            DB::table('industry_features')->where('feature_id', $featureIds['crm'])->whereIn('industry_id', $removeIndustryIds)->delete();
        }

        Schema::dropIfExists('company_hub_audit_events');
        Schema::dropIfExists('company_hub_events');
        Schema::dropIfExists('company_hub_resources');
        Schema::dropIfExists('company_hub_comments');
        Schema::dropIfExists('company_hub_post_acknowledgements');
        Schema::dropIfExists('company_hub_posts');
        Schema::dropIfExists('company_hub_channel_members');
        Schema::dropIfExists('company_hub_channels');
        Schema::dropIfExists('company_hub_settings');
        Schema::dropIfExists('crm_stage_history');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
