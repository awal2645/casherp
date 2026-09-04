<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'property.deposit.manage',
        'property.deposit.refund',
        'hms.manage_security_deposits',
        'hms.approve_security_deposit_refunds',
        'hms.manage_event_venues',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('security_deposits')) {
            Schema::create('security_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('business_location_id')->nullable()->index();
            $table->string('context_type', 32);
            $table->unsignedBigInteger('context_id');
            $table->unsignedInteger('contact_id')->nullable()->index();
            $table->unsignedInteger('currency_id')->nullable();
            $table->decimal('required_amount', 22, 4)->default(0);
            $table->date('due_date')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 32)->default('pending');
            $table->dateTime('settled_at')->nullable();
            $table->dateTime('last_alerted_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'context_type', 'context_id'], 'security_deposits_context_unique');
            $table->index(['business_id', 'status', 'due_date'], 'security_deposits_status_due_idx');
            });
        }

        if (! Schema::hasTable('security_deposit_entries')) {
            Schema::create('security_deposit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('security_deposit_id')->constrained('security_deposits')->cascadeOnDelete();
            $table->unsignedInteger('business_id')->index();
            $table->string('entry_type', 32);
            $table->decimal('amount', 22, 4);
            $table->string('payment_method', 40)->nullable();
            $table->string('reference', 191)->nullable();
            $table->text('description')->nullable();
            $table->date('occurred_on');
            $table->string('evidence_path', 500)->nullable();
            $table->unsignedBigInteger('hms_folio_entry_id')->nullable()->index();
            $table->unsignedBigInteger('business_document_id')->nullable()->index();
            $table->char('idempotency_key', 36)->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedInteger('paid_by')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'security_deposit_entries_idempotency_unique');
            $table->index(['security_deposit_id', 'status', 'entry_type'], 'security_deposit_entries_account_idx');
            });
        }

        if (! Schema::hasTable('hms_event_venues')) {
            Schema::create('hms_event_venues', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('hms_property_id')->index();
            $table->string('name');
            $table->string('code', 40);
            $table->string('venue_type', 40)->default('event_hall');
            $table->unsignedInteger('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'hms_property_id', 'code'], 'hms_event_venues_property_code_unique');
            $table->index(['business_id', 'hms_property_id', 'is_active'], 'hms_event_venues_active_idx');
            });
        }

        if (! Schema::hasTable('business_payment_methods')) {
            Schema::create('business_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('kind', 30);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('accepts_money')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'code'], 'business_payment_methods_business_code_unique');
            });
        }
        $defaults = [
            ['cash', 'Cash', 'cash', true, true],
            ['card', 'Visa / Card', 'card', true, true],
            ['mobile_money', 'Mobile Money', 'mobile_money', true, true],
            ['bank_transfer', 'Bank Transfer', 'bank', true, true],
            ['cheque', 'Cheque', 'cheque', true, true],
            ['credit', 'Credit (Pay Later)', 'credit', false, true],
            ['complimentary', 'Free / Complimentary', 'complimentary', false, true],
            ['other', 'Other', 'other', true, true],
            ['custom_1', 'Custom payment method 1', 'other', true, false],
            ['custom_2', 'Custom payment method 2', 'other', true, false],
            ['custom_3', 'Custom payment method 3', 'other', true, false],
            ['custom_4', 'Custom payment method 4', 'other', true, false],
            ['custom_5', 'Custom payment method 5', 'other', true, false],
        ];
        foreach (DB::table('business')->pluck('id') as $businessId) {
            foreach ($defaults as $sort => [$code, $name, $kind, $acceptsMoney, $enabled]) {
                DB::table('business_payment_methods')->updateOrInsert(
                    ['business_id' => $businessId, 'code' => $code],
                    ['name' => $name, 'kind' => $kind,
                    'is_enabled' => $enabled, 'accepts_money' => $acceptsMoney, 'sort_order' => ($sort + 1) * 10,
                    'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (! Schema::hasTable('property_payment_deposits')) {
            Schema::create('property_payment_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('property_lease_id')->constrained('property_leases')->cascadeOnDelete();
            $table->unsignedInteger('contact_id')->nullable()->index();
            $table->decimal('amount', 22, 4);
            $table->decimal('applied_amount', 22, 4)->default(0);
            $table->date('received_on');
            $table->string('payment_method', 40);
            $table->string('reference', 191)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('available');
            $table->unsignedInteger('receipt_account_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
            $table->index(['business_id', 'property_lease_id', 'status'], 'property_payment_deposits_lease_idx');
            });
        }

        if (! Schema::hasTable('property_payment_deposit_allocations')) {
            Schema::create('property_payment_deposit_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('property_payment_deposit_id');
                $table->unsignedBigInteger('property_rent_due_id');
                $table->decimal('amount', 22, 4);
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! $this->foreignKeyExists('property_payment_deposit_allocations', 'ppd_alloc_deposit_fk')) {
            Schema::table('property_payment_deposit_allocations', function (Blueprint $table) {
                $table->foreign('property_payment_deposit_id', 'ppd_alloc_deposit_fk')
                    ->references('id')->on('property_payment_deposits')->cascadeOnDelete();
            });
        }
        if (! $this->foreignKeyExists('property_payment_deposit_allocations', 'ppd_alloc_due_fk')) {
            Schema::table('property_payment_deposit_allocations', function (Blueprint $table) {
                $table->foreign('property_rent_due_id', 'ppd_alloc_due_fk')
                    ->references('id')->on('property_rent_dues')->cascadeOnDelete();
            });
        }
        if (! $this->indexExists('property_payment_deposit_allocations', 'property_payment_deposit_due_unique')) {
            Schema::table('property_payment_deposit_allocations', function (Blueprint $table) {
                $table->unique(['property_payment_deposit_id', 'property_rent_due_id'], 'property_payment_deposit_due_unique');
            });
        }

        if (Schema::hasTable('property_account_mappings')
            && ! Schema::hasColumn('property_account_mappings', 'payment_advance_liability_account_id')) {
            Schema::table('property_account_mappings', function (Blueprint $table) {
                $table->unsignedInteger('payment_advance_liability_account_id')
                    ->nullable()
                    ->after('tenant_receivable_account_id');
            });
        }

        if (Schema::hasTable('transaction_payments') && ! Schema::hasColumn('transaction_payments', 'payment_purpose')) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->string('payment_purpose', 32)->default('invoice_payment')->after('method')->index();
            });
        }
        if (Schema::hasTable('business_document_payments') && ! Schema::hasColumn('business_document_payments', 'payment_purpose')) {
            Schema::table('business_document_payments', function (Blueprint $table) {
                $table->string('payment_purpose', 32)->default('invoice_payment')->after('method')->index();
            });
        }
        if (Schema::hasTable('business_documents') && ! Schema::hasColumn('business_documents', 'asset_type')) {
            Schema::table('business_documents', function (Blueprint $table) {
                $table->string('asset_type', 40)->nullable()->after('source_id');
                $table->unsignedBigInteger('asset_id')->nullable()->after('asset_type');
                $table->index(['business_id', 'asset_type', 'asset_id'], 'business_document_asset_index');
            });
        }
        if (Schema::hasTable('hms_deposit_schedules')) {
            DB::table('hms_deposit_schedules')->where('purpose', 'reservation_guarantee')->update(['purpose' => 'payment_deposit']);
        }

        $this->backfillPropertyDeposits();
        $this->installPermissions();
        $this->installDocumentTypes();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function backfillPropertyDeposits(): void
    {
        if (! Schema::hasTable('property_leases')) {
            return;
        }
        DB::table('property_leases as lease')
            ->join('property_units as unit', 'unit.id', '=', 'lease.property_unit_id')
            ->join('properties as property', 'property.id', '=', 'unit.property_id')
            ->join('business', 'business.id', '=', 'lease.business_id')
            ->where('lease.security_deposit', '>', 0)
            ->select(['lease.id', 'lease.business_id', 'lease.contact_id', 'lease.security_deposit', 'lease.start_date', 'property.business_location_id', 'business.currency_id'])
            ->orderBy('lease.id')
            ->chunk(100, function ($leases) {
                foreach ($leases as $lease) {
                    DB::table('security_deposits')->updateOrInsert(
                        ['business_id' => $lease->business_id, 'context_type' => 'property_lease', 'context_id' => $lease->id],
                        [
                            'business_location_id' => $lease->business_location_id,
                            'contact_id' => $lease->contact_id,
                            'currency_id' => $lease->currency_id,
                            'required_amount' => $lease->security_deposit,
                            'due_date' => $lease->start_date,
                            'terms' => 'Refundable after inspection, less documented damage or authorized deductions.',
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    private function installPermissions(): void
    {
        $permissionTable = config('permission.table_names.permissions');
        $pivot = config('permission.table_names.role_has_permissions');
        $now = now();
        foreach ($this->permissions as $permission) {
            DB::table($permissionTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        $inheritance = [
            'property.deposit.manage' => ['property.rent.manage'],
            'property.deposit.refund' => ['property.accounting.manage'],
            'hms.manage_security_deposits' => ['hms.manage_folios'],
            'hms.approve_security_deposit_refunds' => ['hms.approve_refunds'],
            'hms.manage_event_venues' => ['hms.manage_properties'],
        ];
        foreach ($inheritance as $newPermission => $oldPermissions) {
            $permissionId = DB::table($permissionTable)->where('name', $newPermission)->where('guard_name', 'web')->value('id');
            $roleIds = DB::table($pivot.' as role_permission')
                ->join($permissionTable.' as permission', 'permission.id', '=', 'role_permission.permission_id')
                ->whereIn('permission.name', $oldPermissions)
                ->pluck('role_permission.role_id')
                ->merge(DB::table('roles')->where('name', 'like', 'Admin#%')->pluck('id'))
                ->unique();
            foreach ($roleIds as $roleId) {
                DB::table($pivot)->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
        Cache::forget(config('permission.cache.key'));
    }

    private function installDocumentTypes(): void
    {
        if (! Schema::hasTable('document_types') || ! Schema::hasTable('industry_document_types') || ! Schema::hasTable('industries')) {
            return;
        }
        $now = now();
        $sort = (int) DB::table('document_types')->max('sort_order');
        foreach (['security_deposit_receipt', 'security_deposit_request', 'damage_assessment', 'damage_charge_invoice', 'security_deposit_settlement'] as $code) {
            $definition = (array) config('smart_documents.types.'.$code, []);
            if (empty($definition)) {
                continue;
            }
            $typeId = DB::table('document_types')->where('code', $code)->value('id');
            if (! $typeId) {
                $sort += 10;
                $typeId = DB::table('document_types')->insertGetId([
                    'code' => $code, 'name' => $definition['name'], 'default_title' => $definition['title'],
                    'category' => $definition['category'], 'default_prefix' => $definition['prefix'],
                    'scenario_code' => $definition['scenario'], 'schema_key' => $definition['schema'],
                    'convert_to_code' => $definition['convert_to'] ?? null,
                    'output_format' => $definition['output_format'] ?? 'a4',
                    'supports_line_items' => $definition['supports_lines'] ?? true,
                    'is_financial' => $definition['is_financial'] ?? false,
                    'requires_acceptance' => $definition['requires_acceptance'] ?? false,
                    'is_active' => true, 'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                // Correct legacy definitions as well: a refundable security
                // deposit is a safeguarded liability register, never revenue.
                DB::table('document_types')->where('id', $typeId)->update([
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
                    'updated_at' => $now,
                ]);
            }
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => 'smart_documents.type.'.$code, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
            $documentPermissionId = DB::table(config('permission.table_names.permissions'))
                ->where('name', 'smart_documents.type.'.$code)
                ->where('guard_name', 'web')
                ->value('id');
            $depositRoleIds = DB::table(config('permission.table_names.role_has_permissions').' as role_permission')
                ->join(config('permission.table_names.permissions').' as permission', 'permission.id', '=', 'role_permission.permission_id')
                ->whereIn('permission.name', [
                    'property.deposit.manage', 'property.deposit.refund',
                    'hms.manage_security_deposits', 'hms.approve_security_deposit_refunds',
                ])
                ->pluck('role_permission.role_id')
                ->merge(DB::table('roles')->where('name', 'like', 'Admin#%')->pluck('id'))
                ->unique();
            foreach ($depositRoleIds as $roleId) {
                DB::table(config('permission.table_names.role_has_permissions'))->insertOrIgnore([
                    'permission_id' => $documentPermissionId, 'role_id' => $roleId,
                ]);
            }
            $industryIds = DB::table('industries')->whereIn('code', [
                'property_management_rentals', 'hotel_lodge_guesthouse', 'hotel_with_restaurant',
            ])->pluck('id');
            foreach ($industryIds as $industryId) {
                DB::table('industry_document_types')->updateOrInsert(
                    ['industry_id' => $industryId, 'document_type_id' => $typeId],
                    ['enabled_by_default' => true, 'sort_order' => $sort, 'created_at' => $now, 'updated_at' => $now]
                );
            }
            if (Schema::hasTable('business_document_settings')) {
                foreach (DB::table('business')->whereIn('industry_id', $industryIds)->pluck('id') as $businessId) {
                    DB::table('business_document_settings')->updateOrInsert(
                        ['business_id' => $businessId, 'document_type_id' => $typeId],
                        ['is_enabled' => true, 'source' => 'industry_default', 'prefix' => $definition['prefix'], 'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        if (Schema::hasTable('document_types')) {
            $addedCodes = ['security_deposit_request', 'damage_assessment', 'damage_charge_invoice', 'security_deposit_settlement'];
            $deletableCodes = [];
            foreach (DB::table('document_types')->whereIn('code', $addedCodes)->get(['id', 'code']) as $type) {
                $hasDocuments = Schema::hasTable('business_documents')
                    && DB::table('business_documents')->where('document_type_id', $type->id)->exists();
                if ($hasDocuments) {
                    // Preserve historical documents and their referential
                    // integrity during rollback; only prevent new use.
                    DB::table('document_types')->where('id', $type->id)->update([
                        'is_active' => false,
                        'updated_at' => now(),
                    ]);
                    continue;
                }
                if (Schema::hasTable('business_document_settings')) {
                    DB::table('business_document_settings')->where('document_type_id', $type->id)->delete();
                }
                if (Schema::hasTable('industry_document_types')) {
                    DB::table('industry_document_types')->where('document_type_id', $type->id)->delete();
                }
                DB::table('document_types')->where('id', $type->id)->delete();
                $deletableCodes[] = $type->code;
            }
            DB::table('document_types')->where('code', 'security_deposit_receipt')->update([
                'category' => 'financial', 'is_financial' => true, 'updated_at' => now(),
            ]);
            $documentPermissionNames = collect($deletableCodes)->map(fn ($code) => 'smart_documents.type.'.$code);
            $documentPermissionIds = DB::table(config('permission.table_names.permissions'))->whereIn('name', $documentPermissionNames)->pluck('id');
            DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $documentPermissionIds)->delete();
            DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $documentPermissionIds)->delete();
            DB::table(config('permission.table_names.permissions'))->whereIn('id', $documentPermissionIds)->delete();
        }
        if (Schema::hasTable('business_documents') && Schema::hasColumn('business_documents', 'asset_type')) {
            Schema::table('business_documents', function (Blueprint $table) {
                $table->dropIndex('business_document_asset_index');
                $table->dropColumn(['asset_type', 'asset_id']);
            });
        }
        if (Schema::hasTable('business_document_payments') && Schema::hasColumn('business_document_payments', 'payment_purpose')) {
            Schema::table('business_document_payments', fn (Blueprint $table) => $table->dropColumn('payment_purpose'));
        }
        if (Schema::hasTable('transaction_payments') && Schema::hasColumn('transaction_payments', 'payment_purpose')) {
            Schema::table('transaction_payments', fn (Blueprint $table) => $table->dropColumn('payment_purpose'));
        }
        if (Schema::hasTable('property_account_mappings')
            && Schema::hasColumn('property_account_mappings', 'payment_advance_liability_account_id')) {
            Schema::table('property_account_mappings', function (Blueprint $table) {
                $table->dropColumn('payment_advance_liability_account_id');
            });
        }
        $permissionIds = DB::table(config('permission.table_names.permissions'))->whereIn('name', $this->permissions)->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.permissions'))->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('property_payment_deposit_allocations');
        Schema::dropIfExists('property_payment_deposits');
        Schema::dropIfExists('business_payment_methods');
        Schema::dropIfExists('hms_event_venues');
        Schema::dropIfExists('security_deposit_entries');
        Schema::dropIfExists('security_deposits');
        Cache::forget(config('permission.cache.key'));
    }
};
