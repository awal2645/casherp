<?php

namespace Tests\Unit;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class ReactDashboardV17Test extends TestCase
{
    public function test_home_defaults_to_react_with_explicit_legacy_rollback(): void
    {
        $home = $this->read('app/Http/Controllers/HomeController.php');
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');

        $this->assertStringContainsString("! request()->boolean('legacy')", $home);
        $this->assertStringContainsString("response()->file(public_path('casherp-workspace.html'))", $home);
        $this->assertStringContainsString("if(path==='/home')return <MainDashboard/>", $source);
        $this->assertStringContainsString('function MainDashboard()', $source);
        $this->assertStringContainsString('legacy_dashboard_url', $source);
    }

    public function test_dashboard_routes_use_authenticated_existing_middleware_group(): void
    {
        $routes = $this->read('routes/web.php');

        $this->assertStringContainsString("Route::get('/home/workspace', [DashboardWorkspaceController::class, 'index'])", $routes);
        $this->assertStringContainsString("Route::put('/home/workspace/preferences', [DashboardWorkspaceController::class, 'updatePreferences'])", $routes);
        $this->assertStringContainsString("Route::get('/notifications/in-app', [InAppNotificationController::class, 'index'])", $routes);
        $this->assertStringContainsString("Route::patch('/notifications/in-app/read-all'", $routes);
        $this->assertStringContainsString("Route::patch('/notifications/in-app/{notification}/read'", $routes);
        $this->assertStringContainsString("['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']", $routes);
    }

    public function test_dashboard_data_is_company_location_and_permission_scoped(): void
    {
        $service = $this->read('app/Services/DashboardWorkspaceService.php');
        $controller = $this->read('app/Http/Controllers/DashboardWorkspaceController.php');

        $this->assertStringContainsString("session()->get('user.business_id')", $service);
        $this->assertStringContainsString('canAccessBusiness($businessId)', $service);
        $this->assertStringContainsString('permitted_locations($businessId)', $service);
        $this->assertStringContainsString("contains('id', (int) \$selected)", $service);
        $this->assertStringContainsString("Rule::exists('business_locations', 'id')->where('business_id', \$businessId)", $controller);
        $this->assertStringContainsString("'financials' => \$canSeeFinancials", $service);
        $this->assertStringContainsString("\$request->user()->can('dashboard.data')", $service);
        $this->assertStringContainsString('whereBetween', $service);
    }

    public function test_dashboard_uses_real_transactions_and_no_placeholder_statistics(): void
    {
        $service = $this->read('app/Services/DashboardWorkspaceService.php');
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');

        foreach (['getSellTotals', 'getPurchaseTotals', 'getTransactionTotals', 'getProductAlert', 'recentTransactions', 'salesTrend'] as $contract) {
            $this->assertStringContainsString($contract, $service);
        }
        $this->assertStringContainsString('data.metrics.net_sales', $source);
        $this->assertStringContainsString('data.recent_transactions.map', $source);
        $this->assertStringNotContainsString('fake statistics', strtolower($source));
        $this->assertStringNotContainsString('Math.random', $source);
    }

    public function test_industry_workspaces_keep_hotel_restaurant_property_and_hr_separate(): void
    {
        $service = $this->read('app/Services/DashboardWorkspaceService.php');

        $this->assertStringContainsString("['hotel_lodge_guesthouse', 'hotel_with_restaurant']", $service);
        $this->assertStringContainsString("['restaurant_food_service', 'hotel_with_restaurant']", $service);
        $this->assertStringContainsString("\$industry === 'property_management_rentals'", $service);
        $this->assertStringContainsString("'/hms/front-desk'", $service);
        $this->assertStringContainsString("'/restaurant-operations'", $service);
        $this->assertStringContainsString("'/property-management'", $service);
        $this->assertStringContainsString("'/hrm/dashboard'", $service);
        $this->assertStringContainsString("enabled('hrm', \$businessId)", $service);
    }

    public function test_preferences_are_per_user_per_company_and_allow_safe_customisation(): void
    {
        $migration = $this->read('database/migrations/2026_09_04_000003_create_user_dashboard_preferences_v17.php');
        $controller = $this->read('app/Http/Controllers/DashboardWorkspaceController.php');
        $service = $this->read('app/Services/DashboardWorkspaceService.php');

        $this->assertStringContainsString("Schema::create('user_dashboard_preferences'", $migration);
        $this->assertStringContainsString("['business_id', 'user_id']", $migration);
        $this->assertStringContainsString("['business_id' => \$businessId, 'user_id' => \$request->user()->id]", $service);
        foreach (['comfortable', 'compact', 'ocean', 'violet', 'emerald', 'sunset'] as $choice) {
            $this->assertStringContainsString("'$choice'", $controller);
        }
        $this->assertStringContainsString("'hidden_sections.*'", $controller);
        $this->assertStringContainsString('array_values(array_unique', $service);
    }

    public function test_frontend_has_accessibility_responsiveness_notifications_and_error_states(): void
    {
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');
        $css = $this->read('public/css/casherp-workspaces.css');

        foreach (['HeaderNotifications', 'aria-expanded', 'aria-label', 'role="img"', 'ErrorBoundary', 'prefers-reduced-motion'] as $contract) {
            $this->assertStringContainsString($contract, $source.$css);
        }
        foreach (['cw-dashboard-hero', 'cw-dashboard-metrics', 'cw-action-grid', 'cw-onboarding', 'cw-notification-panel'] as $class) {
            $this->assertStringContainsString($class, $source);
            $this->assertStringContainsString('.'.$class, $css);
        }
        $this->assertStringContainsString('@media (max-width: 767px)', $css);
    }

    public function test_v17_php_files_parse(): void
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach ([
            'database/migrations/2026_09_04_000003_create_user_dashboard_preferences_v17.php',
            'app/UserDashboardPreference.php',
            'app/Services/DashboardWorkspaceService.php',
            'app/Services/ReactWorkspaceContextService.php',
            'app/Http/Controllers/DashboardWorkspaceController.php',
            'app/Http/Controllers/HomeController.php',
            'routes/web.php',
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
