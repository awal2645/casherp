<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('property_accounting_postings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('debit_account_id')->nullable();
            $table->unsignedInteger('credit_account_id')->nullable();
            $table->decimal('amount', 22, 4);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('debit_entry_id')->nullable();
            $table->unsignedBigInteger('credit_entry_id')->nullable();
            $table->unsignedBigInteger('reversal_of_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('posted_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'source_type', 'source_id'],
                'property_postings_business_source_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('property_accounting_postings');
    }
};
