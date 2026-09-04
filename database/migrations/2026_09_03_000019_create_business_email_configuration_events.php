<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('business_email_configuration_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->uuid('correlation_id')->unique();
            $table->enum('action', ['configuration_saved', 'test_email']);
            $table->enum('status', ['success', 'failure']);
            $table->string('provider', 50)->default('custom');
            $table->string('recipient_masked')->nullable();
            $table->char('recipient_hash', 64)->nullable();
            $table->string('failure_category', 50)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'action', 'created_at'], 'business_email_event_lookup');
        });
    }

    public function down()
    {
        Schema::dropIfExists('business_email_configuration_events');
    }
};
