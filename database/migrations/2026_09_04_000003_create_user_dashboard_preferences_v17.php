<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_dashboard_preferences')) {
            return;
        }

        Schema::create('user_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('default_location_id')->nullable()->index();
            $table->string('date_preset', 24)->default('this_month');
            $table->string('density', 16)->default('comfortable');
            $table->string('accent', 16)->default('ocean');
            $table->json('hidden_sections')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'user_id'], 'user_dashboard_preferences_company_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_dashboard_preferences');
    }
};
