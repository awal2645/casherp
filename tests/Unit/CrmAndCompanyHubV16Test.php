<?php

namespace Tests\Unit;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class CrmAndCompanyHubV16Test extends TestCase
{
    public function test_crm_workspace_reuses_contacts_and_is_tenant_scoped(): void
    {
        $migration = $this->read('database/migrations/2026_09_04_000002_create_crm_workspace_and_company_hub_v16.php');
        $controller = $this->read('Modules/Crm/Http/Controllers/CrmWorkspaceController.php');
        $service = $this->read('Modules/Crm/Services/CrmWorkspaceService.php');

        foreach (['crm_pipelines', 'crm_pipeline_stages', 'crm_opportunities', 'crm_activities', 'crm_stage_history'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        $this->assertStringNotContainsString("Schema::create('crm_leads'", $migration);
        $this->assertStringContainsString("Contact::where('business_id', \$businessId)", $controller);
        $this->assertStringContainsString("where('crm_opportunities.business_id', \$businessId)", $service);
        $this->assertStringContainsString("where('business_id', \$businessId)->where('crm_pipeline_id'", $controller);
        $this->assertStringContainsString('lockForUpdate()', $service);
        $this->assertStringContainsString('crm_pipeline_stage_id', $service);
    }

    public function test_company_hub_is_company_scoped_and_private_by_design(): void
    {
        $migration = $this->read('database/migrations/2026_09_04_000002_create_crm_workspace_and_company_hub_v16.php');
        $access = $this->read('app/Services/CompanyHubAccessService.php');
        $resources = $this->read('app/Http/Controllers/CompanyHubResourceController.php');
        $filesystems = $this->read('config/filesystems.php');

        foreach (['company_hub_channels', 'company_hub_channel_members', 'company_hub_posts', 'company_hub_post_acknowledgements', 'company_hub_comments', 'company_hub_resources', 'company_hub_events', 'company_hub_audit_events'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        $this->assertStringContainsString("where('business_id', \$businessId)", $access);
        $this->assertStringContainsString("\$item->business_id !== \$businessId", $access);
        $this->assertStringContainsString("\$channel->type === 'private'", $access);
        $this->assertStringContainsString("'company_hub_private'", $filesystems);
        $this->assertStringContainsString("storage_path('app/private/company-hub')", $filesystems);
        $this->assertStringContainsString("Storage::disk(\$resource->file_disk)->download", $resources);
        $this->assertStringNotContainsString("public_path('uploads')", $resources);
    }

    public function test_all_six_industries_receive_crm_and_company_hub_defaults(): void
    {
        $migration = $this->read('database/migrations/2026_09_04_000002_create_crm_workspace_and_company_hub_v16.php');
        foreach (['general_business', 'restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant', 'property_management_rentals', 'professional_services'] as $industry) {
            $this->assertStringContainsString("'$industry'", $migration);
        }
        $this->assertStringContainsString("['company_hub' => \$hubFeatureId, 'crm' => \$crmFeatureId]", $migration);
        $this->assertStringContainsString("'source' => 'v16_global_default'", $migration);
        $this->assertStringContainsString("['company_hub', 'crm_pipeline']", $migration);
    }

    public function test_permissions_routes_and_mutating_verbs_are_wired(): void
    {
        $routes = $this->read('routes/web.php').$this->read('Modules/Crm/Routes/web.php');
        $migration = $this->read('database/migrations/2026_09_04_000002_create_crm_workspace_and_company_hub_v16.php');
        $roles = $this->read('app/Http/Controllers/RoleController.php');

        foreach (['company_hub.view', 'company_hub.publish_announcements', 'company_hub.manage_channels', 'company_hub.manage_documents', 'company_hub.manage_events', 'crm.workspace.view', 'crm.pipeline.manage', 'crm.opportunity.manage', 'crm.activity.manage'] as $permission) {
            $this->assertStringContainsString($permission, $migration.$roles);
        }
        $this->assertStringContainsString("middleware('business.feature:company_hub')", $routes);
        $this->assertStringContainsString("Route::post('/posts/{uuid}/opened'", $routes);
        $this->assertStringContainsString("Route::patch('workspace/opportunities/{uuid}/stage'", $routes);
        $this->assertStringContainsString("Route::delete('workspace/opportunities/{uuid}'", $routes);
        $this->assertStringNotContainsString("Route::get('workspace/opportunities/{uuid}/stage'", $routes);
    }

    public function test_rebuilt_frontage_is_react_and_compiled(): void
    {
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');
        $shell = $this->read('public/casherp-workspace.html');
        $css = $this->read('public/css/casherp-workspaces.css');
        $bundle = $this->path('public/js/casherp-workspaces-react.js');
        $controllerSurfaces = $this->read('app/Http/Controllers/CompanyHubController.php')
            .$this->read('app/Http/Controllers/CompanyHubResourceController.php')
            .$this->read('app/Http/Controllers/CompanyHubEventController.php')
            .$this->read('Modules/Crm/Http/Controllers/CrmWorkspaceController.php');

        $this->assertStringContainsString("from 'react'", $source);
        $this->assertStringContainsString('createRoot', $source);
        $this->assertStringContainsString('IntersectionObserver', $source);
        $this->assertStringContainsString('active_business', $source);
        $this->assertStringContainsString('workspace.switch_url', $source);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('casherp-workspaces-react.js', $shell);
        $this->assertStringContainsString("response()->file(public_path('casherp-workspace.html'))", $controllerSurfaces);
        $this->assertStringNotContainsString("view('company_hub", $controllerSurfaces);
        $this->assertStringNotContainsString("view('crm::workspace", $controllerSurfaces);
        $this->assertFileExists($bundle);
        $this->assertGreaterThan(100000, filesize($bundle));
    }

    public function test_notifications_and_reminders_are_end_to_end_wired(): void
    {
        $hub = $this->read('app/Notifications/CompanyHubNotification.php');
        $reminder = $this->read('app/Console/Commands/SendCrmActivityReminders.php');
        $kernel = $this->read('app/Console/Kernel.php');

        $this->assertStringContainsString("['database']", $hub);
        $this->assertStringContainsString("\$channels[] = 'mail'", $hub);
        $this->assertStringContainsString("whereNull('reminder_sent_at')", $reminder);
        $this->assertStringContainsString("->notify(new CrmActivityReminderNotification", $reminder);
        $this->assertStringContainsString("casherp:send-crm-activity-reminders", $kernel);
        $this->assertStringContainsString('everyFiveMinutes()->withoutOverlapping()', $kernel);
    }

    public function test_new_php_files_are_parseable(): void
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach ([
            'database/migrations/2026_09_04_000002_create_crm_workspace_and_company_hub_v16.php',
            'app/Services/CompanyHubAccessService.php',
            'app/Services/ReactWorkspaceContextService.php',
            'app/Http/Controllers/CompanyHubController.php',
            'app/Http/Controllers/CompanyHubResourceController.php',
            'app/Http/Controllers/CompanyHubEventController.php',
            'app/Notifications/CompanyHubNotification.php',
            'app/Notifications/CrmActivityReminderNotification.php',
            'app/Console/Commands/SendCrmActivityReminders.php',
            'Modules/Crm/Services/CrmWorkspaceService.php',
            'Modules/Crm/Http/Controllers/CrmWorkspaceController.php',
        ] as $file) {
            $this->assertNotEmpty($parser->parse($this->read($file)), $file.' did not parse');
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
