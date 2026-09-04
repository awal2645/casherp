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
        if (! Schema::hasTable('document_types')) {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('default_title');
            $table->string('category', 30)->index();
            $table->string('default_prefix', 20);
            $table->string('scenario_code', 50)->index();
            $table->string('schema_key', 50)->nullable();
            $table->string('convert_to_code', 80)->nullable();
            $table->string('output_format', 20)->default('a4');
            $table->boolean('supports_line_items')->default(true);
            $table->boolean('is_financial')->default(false);
            $table->boolean('requires_acceptance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('industry_document_types')) {
        Schema::create('industry_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('industries')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->boolean('enabled_by_default')->default(true);
            $table->string('display_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['industry_id', 'document_type_id'], 'industry_document_type_unique');
        });
        }

        if (! Schema::hasTable('business_document_settings')) {
        Schema::create('business_document_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('source', 30)->default('industry_default');
            $table->string('display_name')->nullable();
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->longText('default_terms')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('terms_reviewed_at')->nullable();
            $table->char('terms_reviewed_hash', 64)->nullable();
            $table->unsignedInteger('terms_reviewed_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'document_type_id'], 'business_document_setting_unique');
        });
        }

        if (! Schema::hasTable('business_documents')) {
        Schema::create('business_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('contact_id')->nullable()->index();
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->foreignId('parent_document_id')->nullable()->constrained('business_documents')->nullOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('document_number', 100);
            $table->string('scenario_code', 50)->index();
            $table->string('title');
            $table->string('subject')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->dateTime('service_start_at')->nullable();
            $table->dateTime('service_end_at')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->decimal('subtotal', 22, 4)->default(0);
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->decimal('total_amount', 22, 4)->default(0);
            $table->decimal('amount_paid', 22, 4)->default(0);
            $table->decimal('balance_due', 22, 4)->default(0);
            $table->longText('notes')->nullable();
            $table->longText('terms')->nullable();
            $table->json('data')->nullable();
            $table->json('business_snapshot');
            $table->json('party_snapshot')->nullable();
            $table->json('template_snapshot');
            $table->char('public_token_hash', 64)->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'document_number'], 'business_document_number_unique');
            $table->index(['business_id', 'document_type_id', 'status'], 'business_document_type_status_index');
            $table->index(['business_id', 'source_type', 'source_id'], 'business_document_source_index');
        });
        }

        if (! Schema::hasTable('business_document_lines')) {
        Schema::create('business_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('line_type', 30)->default('item');
            $table->text('description');
            $table->decimal('quantity', 22, 4)->default(1);
            $table->decimal('unit_price', 22, 4)->default(0);
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->decimal('line_total', 22, 4)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('business_document_signatories')) {
        Schema::create('business_document_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('role');
            $table->string('name')->nullable();
            $table->string('position')->nullable();
            $table->string('identifier')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('business_document_events')) {
        Schema::create('business_document_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
            $table->string('event', 50)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
        }

        $now = now();
        $typeIds = [];
        $sort = 10;
        foreach ((array) config('smart_documents.types', []) as $code => $definition) {
            DB::table('document_types')->updateOrInsert(
                ['code' => $code],
                [
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
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $typeIds[$code] = DB::table('document_types')->where('code', $code)->value('id');
            $sort += 10;
        }

        $industryIds = DB::table('industries')->pluck('id', 'code');
        foreach ((array) config('smart_documents.industry_profiles', []) as $industryCode => $codes) {
            if (empty($industryIds[$industryCode])) {
                continue;
            }
            foreach (array_values($codes) as $index => $code) {
                if (empty($typeIds[$code])) {
                    continue;
                }
                DB::table('industry_document_types')->insert([
                    'industry_id' => $industryIds[$industryCode],
                    'document_type_id' => $typeIds[$code],
                    'enabled_by_default' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('features')->updateOrInsert(
            ['code' => 'smart_documents'],
            [
                'name' => 'Smart Documents',
                'module_key' => 'add_sale',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $featureId = DB::table('features')->where('code', 'smart_documents')->value('id');
        foreach ($industryIds as $industryId) {
            DB::table('industry_features')->updateOrInsert(
                ['industry_id' => $industryId, 'feature_id' => $featureId],
                ['enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if (Schema::hasTable('business') && Schema::hasColumn('business', 'enabled_modules')) {
        DB::table('business')
            ->whereNotNull('industry_id')
            ->select(['id', 'industry_id', 'enabled_modules'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses) use ($featureId, $now, $industryIds, $typeIds) {
                $codeByIndustryId = collect($industryIds)->flip();
                foreach ($businesses as $business) {
                    DB::table('business_features')->updateOrInsert(
                        ['business_id' => $business->id, 'feature_id' => $featureId],
                        ['is_enabled' => true, 'source' => 'industry_default', 'created_at' => $now, 'updated_at' => $now]
                    );

                    $modules = json_decode($business->enabled_modules ?: '[]', true) ?: [];
                    $modules[] = 'add_sale';
                    DB::table('business')->where('id', $business->id)->update([
                        'enabled_modules' => json_encode(array_values(array_unique($modules))),
                    ]);

                    $industryCode = $codeByIndustryId->get($business->industry_id);
                    foreach ((array) config('smart_documents.industry_profiles.'.$industryCode, []) as $typeCode) {
                        if (empty($typeIds[$typeCode])) {
                            continue;
                        }
                        $definition = config('smart_documents.types.'.$typeCode, []);
                        DB::table('business_document_settings')->updateOrInsert(
                            ['business_id' => $business->id, 'document_type_id' => $typeIds[$typeCode]],
                            [
                                'is_enabled' => true,
                                'source' => 'industry_default',
                                'prefix' => $definition['prefix'] ?? strtoupper(substr($typeCode, 0, 6)),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]
                        );
                    }
                }
            });
        }

        $permissions = [
            'smart_documents.view',
            'smart_documents.create',
            'smart_documents.update',
            'smart_documents.issue',
            'smart_documents.void',
            'smart_documents.share',
            'smart_documents.settings',
        ];
        foreach (array_keys((array) config('smart_documents.types', [])) as $typeCode) {
            $permissions[] = 'smart_documents.type.'.$typeCode;
        }
        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        Cache::forget('spatie.permission.cache');
    }

    public function down()
    {
        Cache::forget('spatie.permission.cache');
        $permissions = array_merge([
            'smart_documents.view', 'smart_documents.create', 'smart_documents.update',
            'smart_documents.issue', 'smart_documents.void', 'smart_documents.share',
            'smart_documents.settings',
        ], array_map(fn ($code) => 'smart_documents.type.'.$code, array_keys((array) config('smart_documents.types', []))));
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        if (Schema::hasTable('features')) {
            $featureId = DB::table('features')->where('code', 'smart_documents')->value('id');
            if ($featureId) {
                DB::table('business_features')->where('feature_id', $featureId)->delete();
                DB::table('industry_features')->where('feature_id', $featureId)->delete();
                DB::table('features')->where('id', $featureId)->delete();
            }
        }

        Schema::dropIfExists('business_document_events');
        Schema::dropIfExists('business_document_signatories');
        Schema::dropIfExists('business_document_lines');
        Schema::dropIfExists('business_documents');
        Schema::dropIfExists('business_document_settings');
        Schema::dropIfExists('industry_document_types');
        Schema::dropIfExists('document_types');
    }
};
