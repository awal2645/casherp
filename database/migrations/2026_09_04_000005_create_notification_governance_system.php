<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('registration_intents')) {
            Schema::create('registration_intents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('email')->index();
                $table->char('fingerprint', 64)->index();
                $table->string('business_name')->nullable();
                $table->string('industry_code', 80)->nullable()->index();
                $table->string('country', 100)->nullable();
                $table->string('source', 40)->default('website');
                $table->string('last_step', 40)->default('company_profile');
                $table->boolean('reminder_consent')->default(false);
                $table->timestamp('last_activity_at')->index();
                $table->timestamp('next_reminder_at')->nullable()->index();
                $table->timestamp('reminder_sent_at')->nullable();
                $table->unsignedTinyInteger('reminder_count')->default(0);
                $table->timestamp('completed_at')->nullable()->index();
                $table->unsignedInteger('completed_user_id')->nullable()->index();
                $table->unsignedInteger('completed_business_id')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['reminder_consent', 'completed_at', 'next_reminder_at'], 'registration_intents_reminder_index');
            });
        }

        if (! Schema::hasTable('business_notification_settings')) {
            Schema::create('business_notification_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->string('event_key', 120);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('database_enabled')->default(true);
                $table->boolean('email_enabled')->default(false);
                $table->unsignedSmallInteger('lead_days')->nullable();
                $table->unsignedSmallInteger('overdue_after_hours')->nullable();
                $table->json('recipient_permissions')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'event_key'], 'business_notification_event_unique');
            });
        }

        if (! Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedInteger('business_id')->index();
                $table->string('event_key', 120)->default('*');
                $table->boolean('database_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->string('digest', 20)->default('immediate');
                $table->time('quiet_hours_start')->nullable();
                $table->time('quiet_hours_end')->nullable();
                $table->string('timezone', 64)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'business_id', 'event_key'], 'user_notification_event_unique');
            });
        }

        if (! Schema::hasTable('notification_events')) {
            Schema::create('notification_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedInteger('business_id')->nullable()->index();
                $table->string('event_key', 120)->index();
                $table->string('category', 40)->index();
                $table->string('severity', 20)->default('info')->index();
                $table->string('subject_type', 120)->nullable();
                $table->string('subject_id', 100)->nullable();
                $table->string('title');
                $table->text('message');
                $table->string('action_url', 1000)->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->char('fingerprint', 64)->unique();
                $table->timestamp('resolved_at')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'event_key', 'resolved_at'], 'notification_events_business_open_index');
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->string('recipient_email')->nullable();
                $table->string('channel', 24);
                $table->string('status', 24)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('next_retry_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['notification_event_id', 'user_id', 'channel'], 'notification_delivery_user_unique');
                $table->index(['notification_event_id', 'recipient_email', 'channel'], 'notification_delivery_email_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('business_notification_settings');
        Schema::dropIfExists('registration_intents');
    }
};
