<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('hms_rooms', function (Blueprint $table) {
            $table->string('housekeeping_status')->default('ready')->after('room_number');
            $table->timestamp('last_cleaned_at')->nullable()->after('housekeeping_status');
            $table->timestamp('last_inspected_at')->nullable()->after('last_cleaned_at');
            $table->index('housekeeping_status');
        });

        Schema::create('hms_housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->unsignedBigInteger('hms_room_id')->index();
            $table->unsignedInteger('transaction_id')->nullable()->index();
            $table->unsignedInteger('assigned_to')->nullable()->index();
            $table->string('task_type')->default('checkout_cleaning');
            $table->string('priority')->default('normal');
            $table->string('status')->default('pending')->index();
            $table->dateTime('scheduled_for')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('cleaned_at')->nullable();
            $table->dateTime('inspected_at')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('completed_by')->nullable();
            $table->unsignedInteger('inspected_by')->nullable();
            $table->text('notes')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['business_id', 'hms_room_id', 'transaction_id', 'task_type'],
                'hms_housekeeping_source_unique'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('hms_housekeeping_tasks');

        Schema::table('hms_rooms', function (Blueprint $table) {
            $table->dropIndex(['housekeeping_status']);
            $table->dropColumn([
                'housekeeping_status',
                'last_cleaned_at',
                'last_inspected_at',
            ]);
        });
    }
};
