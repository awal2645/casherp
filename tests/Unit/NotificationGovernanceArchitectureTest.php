<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationGovernanceArchitectureTest extends TestCase
{
    public function test_governance_schema_tracks_consent_preferences_events_and_delivery_attempts(): void
    {
        $migration = $this->read('database/migrations/2026_09_04_000005_create_notification_governance_system.php');

        foreach (['registration_intents', 'business_notification_settings', 'user_notification_preferences', 'notification_events', 'notification_deliveries'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        foreach (['reminder_consent', 'fingerprint', 'resolved_at', 'attempts', 'next_retry_at', 'last_error'] as $field) {
            $this->assertStringContainsString("'$field'", $migration);
        }
        $registrationSchema = substr($migration, strpos($migration, "Schema::create('registration_intents'"), strpos($migration, "Schema::create('business_notification_settings'") - strpos($migration, "Schema::create('registration_intents'"));
        $this->assertStringContainsString("char('fingerprint', 64)", $registrationSchema);
        $this->assertStringContainsString('notification_delivery_user_unique', $migration);
    }

    public function test_catalog_covers_exact_six_industries_and_hr_remains_available_to_every_industry(): void
    {
        $catalog = require $this->path('config/notification_events.php');
        $expected = [
            'general_business',
            'restaurant_food_service',
            'hotel_lodge_guesthouse',
            'hotel_with_restaurant',
            'property_management_rentals',
            'professional_services',
        ];

        $this->assertSame($expected, array_keys($catalog['industry_categories']));
        foreach ($catalog['industry_categories'] as $categories) {
            $this->assertContains('hr', $categories);
        }
        $this->assertContains('hospitality', $catalog['industry_categories']['hotel_lodge_guesthouse']);
        $this->assertNotContains('hospitality', $catalog['industry_categories']['restaurant_food_service']);
        $this->assertContains('restaurant', $catalog['industry_categories']['hotel_with_restaurant']);
        $this->assertContains('property', $catalog['industry_categories']['property_management_rentals']);
    }

    public function test_scheduled_audit_handles_recovery_expiry_overdue_and_industry_operations(): void
    {
        $command = $this->read('app/Console/Commands/AuditOperationalNotifications.php');
        $kernel = $this->read('app/Console/Kernel.php');

        foreach ([
            'registration.abandoned', 'trial.expired', 'subscription.expiring', 'subscription.payment_failed',
            'sales.invoice_overdue', 'inventory.low_stock', 'procurement.approval_overdue',
            'restaurant.reservation_attention', 'hospitality.arrival_overdue', 'property.lease_expired',
            'deposit.refund_overdue', 'hr.payroll_action_required', 'crm.activity_overdue',
            'company_hub.acknowledgement_overdue', 'data_import.failed',
        ] as $event) {
            $this->assertStringContainsString("'$event'", $command);
        }
        $this->assertStringContainsString('reconcileResolvedEvents', $command);
        $this->assertStringContainsString("where('data->event_uuid'", $command);
        $this->assertStringContainsString("command('casherp:audit-notifications')", $kernel);
        $this->assertStringContainsString('withoutOverlapping()', $kernel);
        $this->assertStringContainsString('onOneServer()', $kernel);
        $this->assertStringContainsString('(int) $delivery->attempts >= 5', $this->read('app/Services/NotificationOrchestratorService.php'));
        $this->assertStringContainsString('next_retry_at', $this->read('app/Services/NotificationOrchestratorService.php'));
    }

    public function test_registration_recovery_is_consent_based_limited_and_has_signed_stop_link(): void
    {
        $controller = $this->read('app/Http/Controllers/LandingRegistrationController.php');
        $notification = $this->read('app/Notifications/AbandonedRegistrationNotification.php');
        $routes = $this->read('routes/web.php');

        $this->assertStringContainsString("'reminder_consent'", $controller);
        $this->assertStringContainsString("'next_reminder_at' => \$request->boolean('reminder_consent') ? now()->addDay() : null", $controller);
        $this->assertStringContainsString("->where('reminder_count', '<', 2)", $this->read('app/Console/Commands/AuditOperationalNotifications.php'));
        $this->assertSame(2, substr_count($notification, 'temporarySignedRoute'));
        $this->assertStringContainsString("'landing.registration.resume'", $notification);
        $this->assertStringContainsString("->middleware(['signed'", $routes);
        $this->assertStringContainsString("hash_hmac(", $controller);
        $this->assertStringContainsString("where('fingerprint', \$fingerprint)", $controller);
        $this->assertStringContainsString("->name('landing.registration.resume')", $routes);
        $this->assertStringContainsString('stopReminders', $routes);
    }

    public function test_notification_api_is_active_company_scoped_and_policy_authorised(): void
    {
        $controller = $this->read('app/Http/Controllers/InAppNotificationController.php');
        $routes = $this->read('routes/web.php');

        foreach (['/notifications/in-app', '/notifications/preferences', '/notifications/settings'] as $uri) {
            $this->assertStringContainsString("'$uri'", $routes);
        }
        $this->assertStringContainsString("session()->get('user.business_id')", $controller);
        $this->assertStringContainsString("where('data->business_id', \$businessId)", $controller);
        $this->assertStringContainsString('SuperadminCommunicator::class', $controller);
        $this->assertStringContainsString('canAccessBusiness($businessId)', $controller);
        $this->assertStringContainsString("canForBusiness('business_settings.access'", $controller);
        $this->assertStringContainsString('critical_in_app_locked', $controller);
        $mail = $this->read('app/Notifications/OperationalAlertNotification.php');
        $this->assertStringContainsString('BusinessMailConfigurationService::class', $mail);
        $this->assertStringContainsString('$this->event->business_id', $mail);
    }

    public function test_existing_database_notifications_are_preserved_and_company_scoped(): void
    {
        $files = [
            'Modules/AssetManagement/Notifications/AssetSentForMaintenance.php',
            'Modules/AssetManagement/Notifications/AssetAssignedForMaintenance.php',
            'Modules/Essentials/Notifications/PayrollNotification.php',
            'Modules/Essentials/Notifications/NewTaskNotification.php',
            'Modules/Essentials/Notifications/NewTaskDocumentNotification.php',
            'Modules/Essentials/Notifications/NewTaskCommentNotification.php',
            'Modules/Essentials/Notifications/NewMessageNotification.php',
            'Modules/Essentials/Notifications/NewLeaveNotification.php',
            'Modules/Essentials/Notifications/LeaveStatusNotification.php',
            'Modules/Essentials/Notifications/DocumentShareNotification.php',
            'Modules/Project/Notifications/NewTaskAssignedNotification.php',
            'Modules/Project/Notifications/NewProjectAssignedNotification.php',
            'Modules/Project/Notifications/NewCommentOnTaskNotification.php',
        ];

        foreach ($files as $file) {
            $this->assertStringContainsString("'business_id'", $this->read($file), $file.' must stay in its company context.');
        }
        $this->assertStringContainsString("'business_id' => (int) \$todo->business_id", $this->read('Modules/Essentials/Http/Controllers/ToDoController.php'));
        $this->assertStringContainsString("'business_id' => (int) \$maintenance->business_id", $this->read('Modules/AssetManagement/Utils/AssetUtil.php'));
    }

    public function test_react_notification_center_has_filters_recovery_and_company_controls(): void
    {
        $react = $this->read('resources/js/casherp-workspaces-react.jsx');
        $css = $this->read('public/css/casherp-workspaces.css');

        foreach (['criticalUnread', 'Notification controls', 'Company notification policy', 'failed deliveries', 'Showing this active company only'] as $contract) {
            $this->assertStringContainsString($contract, $react);
        }
        foreach (['cw-notification-panel', 'cw-notification-filters', 'cw-notification-settings', '@media (max-width: 767px)'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->path($relative));
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
