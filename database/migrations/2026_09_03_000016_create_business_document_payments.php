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
        if (! Schema::hasTable('business_documents')) {
            return;
        }

        $created = ! Schema::hasTable('business_document_payments');
        if ($created) {
            Schema::create('business_document_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->foreignId('business_document_id')->constrained('business_documents')->cascadeOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 22, 4);
                $table->unsignedInteger('currency_id')->nullable();
                $table->decimal('exchange_rate', 22, 8)->default(1);
                $table->string('method', 50);
                $table->string('reference', 191)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('posted')->index();
                $table->timestamp('reversed_at')->nullable();
                $table->unsignedInteger('reversed_by')->nullable();
                $table->string('reversal_reason')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();
                $table->index(['business_id', 'business_document_id', 'status'], 'business_document_payment_scope_index');
                $table->unique(['business_document_id', 'reference'], 'business_document_payment_reference_unique');
            });
        }

        $now = now();
        if ($created) {
            DB::table('business_documents as bd')
                ->join('document_types as dt', 'dt.id', '=', 'bd.document_type_id')
                ->where('bd.amount_paid', '>', 0)
                ->where('dt.code', 'not like', '%receipt%')
                ->select(['bd.id', 'bd.business_id', 'bd.issue_date', 'bd.amount_paid', 'bd.currency_id', 'bd.exchange_rate', 'bd.created_by'])
                ->orderBy('bd.id')
                ->chunkById(200, function ($documents) use ($now) {
                    foreach ($documents as $document) {
                        DB::table('business_document_payments')->insert([
                            'business_id' => $document->business_id,
                            'business_document_id' => $document->id,
                            'payment_date' => $document->issue_date,
                            'amount' => $document->amount_paid,
                            'currency_id' => $document->currency_id,
                            'exchange_rate' => $document->exchange_rate ?: 1,
                            'method' => 'opening_balance',
                            'reference' => 'OPEN-'.$document->id,
                            'notes' => 'Opening payment migrated from the issued document balance.',
                            'status' => 'posted',
                            'created_by' => $document->created_by,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }, 'bd.id', 'id');
        }

        foreach (['smart_documents.payment.create', 'smart_documents.payment.reverse'] as $permission) {
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
        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->whereIn('name', ['smart_documents.payment.create', 'smart_documents.payment.reverse'])
                ->pluck('id');
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            }
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
        Schema::dropIfExists('business_document_payments');
    }
};
