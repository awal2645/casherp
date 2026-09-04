<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_log')) {
            if (! Schema::hasColumn('activity_log', 'business_id')) {
                Schema::table('activity_log', function (Blueprint $table) {
                    $table->unsignedInteger('business_id')->nullable();
                });
            }

            return;
        }

        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down(): void
    {
        // Keep activity_log; login writes to it.
    }
};
