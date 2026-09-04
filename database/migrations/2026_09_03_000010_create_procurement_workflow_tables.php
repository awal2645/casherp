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
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('transaction_id')->unique();
            $table->unsignedInteger('parent_transaction_id')->nullable()->index();
            $table->string('document_type', 30)->index();
            $table->unsignedInteger('department_id')->nullable()->index();
            $table->unsignedInteger('project_id')->nullable()->index();
            $table->unsignedInteger('currency_id')->nullable()->index();
            $table->unsignedInteger('requested_by')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->text('purpose')->nullable();
            $table->decimal('budget_amount', 22, 4)->nullable();
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->string('approval_status', 40)->default('pending_manager')->index();
            $table->string('current_stage', 30)->default('manager')->nullable()->index();
            $table->unsignedBigInteger('selected_quote_id')->nullable()->index();
            $table->unsignedInteger('auto_expense_transaction_id')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('parent_transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->foreign('auto_expense_transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index(['business_id', 'document_type', 'approval_status'], 'proc_docs_business_status_index');
        });

        Schema::create('procurement_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('stage', 30);
            $table->string('required_permission', 100);
            $table->string('status', 20)->default('waiting')->index();
            $table->unsignedInteger('acted_by')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['procurement_document_id', 'stage'], 'proc_approval_document_stage_unique');
        });

        Schema::create('procurement_supplier_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('requisition_transaction_id')->index();
            $table->unsignedInteger('supplier_id')->index();
            $table->unsignedInteger('currency_id')->index();
            $table->unsignedInteger('tax_id')->nullable()->index();
            $table->string('quote_reference', 100)->nullable();
            $table->decimal('exchange_rate', 22, 8)->default(1);
            $table->decimal('subtotal', 22, 4);
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->decimal('shipping_amount', 22, 4)->default(0);
            $table->decimal('total', 22, 4);
            $table->date('delivery_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('line_prices');
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 20)->default('received')->index();
            $table->unsignedInteger('entered_by');
            $table->timestamps();

            $table->foreign('requisition_transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->index(['business_id', 'requisition_transaction_id'], 'proc_quotes_business_requisition_index');
        });

        Schema::table('procurement_documents', function (Blueprint $table) {
            $table->foreign('selected_quote_id')
                ->references('id')->on('procurement_supplier_quotes')->nullOnDelete();
        });

        Schema::create('procurement_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_document_id')->constrained('procurement_documents')->cascadeOnDelete();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('event', 60)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_document_id')->nullable()->index()->after('source');
            $table->unsignedInteger('procurement_department_id')->nullable()->index()->after('procurement_document_id');
            $table->unsignedInteger('procurement_currency_id')->nullable()->index()->after('procurement_department_id');
            $table->foreign('procurement_document_id')
                ->references('id')->on('procurement_documents')->nullOnDelete();
        });

        $now = now();
        DB::table('features')->updateOrInsert(
            ['code' => 'procurement'],
            ['name' => 'Procurement & Approvals', 'module_key' => 'purchases', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
        );
        $featureId = DB::table('features')->where('code', 'procurement')->value('id');
        foreach (DB::table('industries')->pluck('id') as $industryId) {
            DB::table('industry_features')->updateOrInsert(
                ['industry_id' => $industryId, 'feature_id' => $featureId],
                ['enabled_by_default' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
        DB::table('business')->whereNotNull('industry_id')
            ->orderBy('id')
            ->chunkById(100, function ($businesses) use ($featureId, $now) {
                foreach ($businesses as $business) {
                    DB::table('business_features')->updateOrInsert(
                        ['business_id' => $business->id, 'feature_id' => $featureId],
                        ['is_enabled' => true, 'source' => 'industry_default', 'created_at' => $now, 'updated_at' => $now]
                    );

                    $enabledModules = json_decode($business->enabled_modules ?: '[]', true) ?: [];
                    $commonSettings = json_decode($business->common_settings ?: '[]', true) ?: [];
                    $enabledModules[] = 'purchases';
                    $commonSettings['enable_purchase_requisition'] = 1;
                    $commonSettings['enable_purchase_order'] = 1;
                    DB::table('business')->where('id', $business->id)->update([
                        'enabled_modules' => json_encode(array_values(array_unique($enabledModules))),
                        'common_settings' => json_encode($commonSettings),
                        'updated_at' => $now,
                    ]);
                }
            }, 'id');

        $permissions = [
            'procurement.submit',
            'procurement.quote.manage',
            'procurement.approve.manager',
            'procurement.approve.finance',
            'procurement.approve.admin',
            'procurement.audit.view',
        ];
        foreach ($permissions as $permission) {
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
        Cache::forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $featureId = DB::table('features')->where('code', 'procurement')->value('id');
        if ($featureId) {
            DB::table('business_features')->where('feature_id', $featureId)->delete();
            DB::table('industry_features')->where('feature_id', $featureId)->delete();
            DB::table('features')->where('id', $featureId)->delete();
        }

        $driver = DB::connection()->getDriverName();
        Schema::table('transactions', function (Blueprint $table) use ($driver) {
            if ($driver !== 'sqlite') {
                $table->dropForeign(['procurement_document_id']);
            }
            $table->dropIndex(['procurement_document_id']);
            $table->dropIndex(['procurement_department_id']);
            $table->dropIndex(['procurement_currency_id']);
            $table->dropColumn([
                'procurement_document_id',
                'procurement_department_id',
                'procurement_currency_id',
            ]);
        });

        Schema::dropIfExists('procurement_events');
        if ($driver === 'sqlite') {
            Schema::dropIfExists('procurement_approval_steps');
            Schema::dropIfExists('procurement_documents');
            Schema::dropIfExists('procurement_supplier_quotes');
        } else {
            Schema::table('procurement_documents', function (Blueprint $table) {
                $table->dropForeign(['selected_quote_id']);
            });
            Schema::dropIfExists('procurement_supplier_quotes');
            Schema::dropIfExists('procurement_approval_steps');
            Schema::dropIfExists('procurement_documents');
        }

        $permissionIds = DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', [
                'procurement.submit', 'procurement.quote.manage',
                'procurement.approve.manager', 'procurement.approve.finance',
                'procurement.approve.admin', 'procurement.audit.view',
            ])->pluck('id');
        DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $permissionIds)->delete();
        DB::table(config('permission.table_names.permissions'))->whereIn('id', $permissionIds)->delete();
        Cache::forget(config('permission.cache.key'));
    }
};
