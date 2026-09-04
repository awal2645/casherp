<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->string('provider', 32);
            $table->string('provider_user_id', 255);
            $table->string('provider_email', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'provider_user_id'], 'social_accounts_provider_identity_unique');
            $table->unique(['user_id', 'provider'], 'social_accounts_user_provider_unique');
            $table->index(['provider', 'provider_email'], 'social_accounts_provider_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
