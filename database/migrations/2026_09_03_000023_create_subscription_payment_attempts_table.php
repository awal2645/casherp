<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payment_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('reference', 64)->unique();
            $table->string('gateway', 50)->index();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('package_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->decimal('amount', 22, 4);
            $table->string('currency_code', 3);
            $table->string('coupon_code', 100)->nullable();
            $table->json('package_snapshot');
            $table->string('gateway_token', 191)->nullable()->unique();
            $table->string('gateway_reference', 191)->nullable()->index();
            $table->string('status', 30)->default('initiated')->index();
            $table->string('provider_result_code', 20)->nullable();
            $table->string('provider_result_message', 500)->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->timestamps();

            $table->index(['business_id', 'package_id', 'status'], 'subscription_attempt_business_package_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_attempts');
    }
};
