<?php

namespace Tests\Unit;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class SalesAccountingReactV19Test extends TestCase
{
    public function test_sales_and_accounting_have_authenticated_react_workspace_routes(): void
    {
        $routes = $this->read('routes/web.php');

        foreach ([
            "Route::get('/sales', [CommercialWorkspaceController::class, 'shell'])",
            "Route::get('/sales/workspace', [CommercialWorkspaceController::class, 'workspace'])",
            "Route::get('/accounting', [AccountingWorkspaceController::class, 'shell'])",
            "Route::get('/accounting/workspace', [AccountingWorkspaceController::class, 'workspace'])",
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
        $this->assertStringContainsString("['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']", $routes);
    }

    public function test_sales_navigation_replaces_the_confusing_duplicate_sell_menu(): void
    {
        $menu = $this->read('app/Http/Middleware/AdminSidebarMenu.php');

        $this->assertStringContainsString("'Sales'", $menu);
        $this->assertStringContainsString("'Sales workspace'", $menu);
        $this->assertStringContainsString("'Create invoice'", $menu);
        $this->assertStringContainsString("'Open point of sale'", $menu);
        $this->assertStringNotContainsString("__('lang_v1.add_draft')", $menu);
        $this->assertStringNotContainsString("__('lang_v1.list_drafts')", $menu);
        $this->assertStringNotContainsString("__('lang_v1.add_quotation')", $menu);
        $this->assertStringNotContainsString("__('lang_v1.list_quotations')", $menu);
    }

    public function test_sales_workspace_covers_the_global_order_to_cash_sections(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        $react = $this->read('resources/js/casherp-workspaces-react.jsx');

        foreach (['overview', 'invoices', 'orders', 'quotations', 'drafts', 'returns', 'fulfilment', 'payments', 'documents', 'customers'] as $view) {
            $this->assertStringContainsString("'$view'", $service, $view);
            $this->assertStringContainsString("$view:", $react, $view);
        }
        foreach (['SalesOverview', 'Sales orders', 'Draft invoices', 'Returns and credits', 'Fulfilment queue'] as $contract) {
            $this->assertStringContainsString($contract, $react);
        }
    }

    public function test_all_six_industries_keep_distinct_sales_experiences(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        $migration = $this->read('database/migrations/2026_09_02_000001_add_industry_feature_profiles.php');

        foreach (['general_business', 'restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant', 'property_management_rentals', 'professional_services'] as $industry) {
            $this->assertStringContainsString("'$industry'", $migration);
        }
        foreach (['restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant', 'property_management_rentals', 'professional_services'] as $specialist) {
            $this->assertStringContainsString("'$specialist' => [", $service);
        }
        $this->assertStringContainsString("'eyebrow' => 'Sales and distribution'", $service);
        $this->assertStringContainsString('Hotel Management while controlling guest folios', $service);
        $this->assertStringContainsString('leases, units, rent schedules and refundable security deposits stay in Property Management', $service);
        $this->assertStringContainsString('Projects and CRM', $service);
        $this->assertStringContainsString("features->enabled(\$shortcut['feature'], (int) \$business->id)", $service);
        $this->assertStringContainsString("canForBusiness(\$ability, (int) \$business->id)", $service);
    }

    public function test_sales_queries_are_company_location_and_role_scoped(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');

        $this->assertStringContainsString("where('transactions.business_id', \$businessId)", $service);
        $this->assertStringContainsString('permitted_locations($businessId)', $service);
        foreach (['order_view_all', 'order_view_own', 'draft_view_all', 'draft_view_own', 'return_view_all', 'return_view_own', 'fulfilment_view_all', 'fulfilment_view_own'] as $permission) {
            $this->assertStringContainsString("'$permission'", $service);
        }
        $this->assertStringContainsString("where(\$prefix.'.created_by', \$user->id)", $service);
    }

    public function test_accounting_workspace_uses_real_ledgers_and_outstanding_transactions(): void
    {
        $service = $this->read('app/Services/AccountingWorkspaceService.php');

        foreach (['AccountTransaction::query()', "where('accounts.business_id', \$businessId)", "where('transactions.business_id', \$businessId)", 'receivables', 'payables', 'cashMovementTrend'] as $contract) {
            $this->assertStringContainsString($contract, $service);
        }
        $this->assertStringNotContainsString('Math.random', $this->read('resources/js/casherp-workspaces-react.jsx'));
    }

    public function test_accounting_respects_location_accounts_and_refundable_deposit_boundary(): void
    {
        $controller = $this->read('app/Http/Controllers/AccountingWorkspaceController.php');
        $service = $this->read('app/Services/AccountingWorkspaceService.php');
        $react = $this->read('resources/js/casherp-workspaces-react.jsx');

        $this->assertStringContainsString('canAccessBusiness($businessId)', $controller.$service);
        $this->assertStringContainsString("can('account.access')", $controller.$service);
        $this->assertStringContainsString('permitted_locations($businessId)', $service);
        $this->assertStringContainsString('default_payment_accounts', $service);
        $this->assertStringContainsString("payment_purpose <> 'security_deposit'", $service);
        $this->assertStringContainsString("whereNotIn('status', ['settled', 'waived'])", $service);
        $this->assertStringContainsString('Refundable deposits stay separate', $react);
    }

    public function test_legacy_list_entries_are_react_first_with_explicit_rollback(): void
    {
        foreach ([
            'app/Http/Controllers/AccountController.php',
            'app/Http/Controllers/SalesOrderController.php',
            'app/Http/Controllers/SellController.php',
            'app/Http/Controllers/SellReturnController.php',
        ] as $file) {
            $source = $this->read($file);
            $this->assertStringContainsString("response()->file(public_path('casherp-workspace.html'))", $source, $file);
            $this->assertStringContainsString("boolean('legacy')", $source, $file);
        }
        foreach (['/sells?legacy=1', '/sales-order?legacy=1', '/sells/drafts?legacy=1', '/sell-return?legacy=1', '/shipments?legacy=1', '/account/account?legacy=1'] as $url) {
            $this->assertStringContainsString($url, $this->read('app/Services/CommercialWorkspaceService.php').$this->read('app/Services/AccountingWorkspaceService.php'));
        }
    }

    public function test_react_ui_is_responsive_accessible_and_compiles_from_source_contracts(): void
    {
        $react = $this->read('resources/js/casherp-workspaces-react.jsx');
        $css = $this->read('public/css/casherp-workspaces.css');

        foreach (['AccountingWorkspace', 'AccountingFilters', 'AccountingResults', 'AccountingOverview', 'LedgerTable', 'AccountingPagination'] as $component) {
            $this->assertStringContainsString("function $component", $react);
        }
        foreach (['aria-label="Sales sections"', 'aria-label="Accounting sections"', 'aria-label="Accounting results pages"'] as $accessibility) {
            $this->assertStringContainsString($accessibility, $react);
        }
        foreach (['cw-work-queue', 'cw-accounting-summary', 'cw-cash-bars'] as $class) {
            $this->assertStringContainsString($class, $react);
            $this->assertStringContainsString('.'.$class, $css);
        }
        $this->assertStringContainsString('@media (max-width: 767px)', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }

    public function test_v19_php_files_parse(): void
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach ([
            'app/Http/Controllers/AccountingWorkspaceController.php',
            'app/Http/Controllers/CommercialWorkspaceController.php',
            'app/Services/AccountingWorkspaceService.php',
            'app/Services/CommercialWorkspaceService.php',
            'app/Services/ReactWorkspaceContextService.php',
            'app/Http/Middleware/AdminSidebarMenu.php',
            'app/Http/Controllers/AccountController.php',
            'app/Http/Controllers/SalesOrderController.php',
            'app/Http/Controllers/SellController.php',
            'app/Http/Controllers/SellReturnController.php',
            'routes/web.php',
        ] as $file) {
            $this->assertNotEmpty($parser->parse($this->read($file)), $file.' did not parse');
        }
    }

    private function read(string $relative): string
    {
        return file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }
}
