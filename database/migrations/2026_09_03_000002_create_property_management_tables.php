<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id(); $table->unsignedInteger('business_id')->index(); $table->string('name'); $table->string('type')->default('residential');
            $table->text('address')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        Schema::create('property_units', function (Blueprint $table) {
            $table->id(); $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete(); $table->string('unit_code');
            $table->string('unit_type')->nullable(); $table->decimal('monthly_rent', 22, 4)->default(0); $table->string('status')->default('vacant'); $table->timestamps();
            $table->unique(['property_id', 'unit_code']);
        });
        Schema::create('property_leases', function (Blueprint $table) {
            $table->id(); $table->unsignedInteger('business_id')->index(); $table->foreignId('property_unit_id')->constrained('property_units')->cascadeOnDelete();
            $table->unsignedInteger('contact_id')->nullable()->index(); $table->date('start_date'); $table->date('end_date')->nullable();
            $table->decimal('monthly_rent', 22, 4); $table->decimal('security_deposit', 22, 4)->default(0); $table->string('status')->default('active'); $table->timestamps();
        });
    }
    public function down() { Schema::dropIfExists('property_leases'); Schema::dropIfExists('property_units'); Schema::dropIfExists('properties'); }
};
