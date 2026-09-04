<?php

namespace Tests\Unit;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class ReactCommercialWorkspaceV18Test extends TestCase
{
    public function test_commercial_shell_api_and_customer_creation_are_authenticated_routes(): void
    {
        $routes = $this->read('routes/web.php');

        $this->assertStringContainsString("use App\\Http\\Controllers\\CommercialWorkspaceController;", $routes);
        $this->assertStringContainsString("Route::get('/commercial', [CommercialWorkspaceController::class, 'shell'])", $routes);
        $this->assertStringContainsString("Route::get('/commercial/workspace', [CommercialWorkspaceController::class, 'workspace'])", $routes);
        $this->assertStringContainsString("Route::post('/commercial/customers', [CommercialWorkspaceController::class, 'storeCustomer'])", $routes);
        $this->assertStringContainsString("['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']", $routes);
    }

    public function test_high_frequency_legacy_lists_are_react_first_with_explicit_rollback(): void
    {
        foreach ([
            'app/Http/Controllers/SellController.php',
            'app/Http/Controllers/ContactController.php',
            'app/Http/Controllers/BusinessDocumentController.php',
        ] as $file) {
            $source = $this->read($file);
            $this->assertStringContainsString("response()->file(public_path('casherp-workspace.html'))", $source, $file);
            $this->assertStringContainsString("boolean('legacy')", $source, $file);
        }
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        foreach (['/sells?legacy=1', '/sells/quotations?legacy=1', '/smart-documents?legacy=1', '/contacts?type=customer&legacy=1'] as $rollback) {
            $this->assertStringContainsString($rollback, $service);
        }
    }

    public function test_api_is_scoped_to_active_company_and_permitted_locations(): void
    {
        $controller = $this->read('app/Http/Controllers/CommercialWorkspaceController.php');
        $service = $this->read('app/Services/CommercialWorkspaceService.php');

        $this->assertStringContainsString("session()->get('user.business_id')", $controller.$service);
        $this->assertStringContainsString('canAccessBusiness($businessId)', $controller.$service);
        $this->assertStringContainsString('Rule::exists(\'business_locations\', \'id\')->where(\'business_id\', $businessId)', $controller);
        $this->assertStringContainsString('permitted_locations($businessId)', $service);
        $this->assertStringContainsString('where(\'transactions.business_id\', $businessId)', $service);
        $this->assertStringContainsString('BusinessDocument::forBusiness($business->id)', $service);
        $this->assertStringContainsString('where(\'contacts.business_id\', $businessId)', $service);
    }

    public function test_sale_and_quote_visibility_honours_all_own_and_commission_permissions(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');

        foreach (['sales_view_all', 'sales_view_own', 'commission_sales', 'quotation_view_all', 'quotation_view_own'] as $contract) {
            $this->assertStringContainsString("'$contract'", $service);
        }
        $this->assertStringContainsString('where($prefix.\'.created_by\', $user->id)', $service);
        $this->assertStringContainsString('orWhere($prefix.\'.commission_agent\', $user->id)', $service);
        $this->assertStringContainsString("orWhereIn('transactions.sub_status', ['quotation', 'proforma'])", $service);
        $this->assertStringContainsString("where('transactions.status', 'final')", $service);
    }

    public function test_document_library_reuses_industry_type_role_and_property_access_controls(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');

        foreach (['IndustryDocumentCatalogService', 'BusinessDocumentAccessService', 'PropertyAccessService'] as $boundary) {
            $this->assertStringContainsString($boundary, $service);
        }
        $this->assertStringContainsString('permittedTypes($business, $user', $service);
        $this->assertStringContainsString('whereIn(\'document_type_id\', $allowedIds)', $service);
        foreach (['smart-documents.show', 'smart-documents.preview', 'smart-documents.print', 'smart-documents.download', 'smart-documents.edit'] as $route) {
            $this->assertStringContainsString($route, $service);
        }
    }

    public function test_security_deposits_are_excluded_from_income_payment_totals(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');

        $this->assertGreaterThanOrEqual(3, substr_count($service, "payment_purpose"));
        $this->assertGreaterThanOrEqual(3, substr_count($service, "security_deposit"));
        $this->assertStringContainsString('Refundable security deposits are held liabilities', $source);
        $this->assertStringContainsString('Security deposits excluded', $source);
    }

    public function test_transaction_documents_expose_preview_print_download_share_and_smart_document_actions(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');

        foreach (['sell.document.preview', 'sell.document.print', 'sell.downloadPdf', 'quotation.downloadPdf', 'sell.document.share'] as $contract) {
            $this->assertStringContainsString($contract, $service);
        }
        foreach (['Preview', 'Print', 'Download', 'Share', 'Edit', 'Smart document'] as $action) {
            $this->assertStringContainsString("'$action'", $source);
        }
        $this->assertStringContainsString('function CommercialActions', $source);
    }

    public function test_customer_quick_create_has_server_validation_and_company_permission_boundary(): void
    {
        $controller = $this->read('app/Http/Controllers/CommercialWorkspaceController.php');
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');

        $this->assertStringContainsString('canForBusiness(\'customer.create\', $businessId)', $controller);
        $this->assertStringContainsString("'supplier_business_name' => ['nullable', 'required_if:contact_type_radio,business'", $controller);
        $this->assertStringContainsString("'mobile' => ['required'", $controller);
        $this->assertStringContainsString("'email' => ['nullable', 'email:rfc'", $controller);
        $this->assertStringContainsString('DB::transaction', $controller);
        $this->assertStringContainsString('ContactCreatedOrModified', $controller);
        $this->assertStringContainsString('title="Add customer"', $source);
    }

    public function test_react_commercial_workspace_is_responsive_accessible_and_has_real_filters(): void
    {
        $source = $this->read('resources/js/casherp-workspaces-react.jsx');
        $css = $this->read('public/css/casherp-workspaces.css');

        foreach (['CommercialWorkspace', 'CommercialFilters', 'CommercialResults', 'ReceiptResults', 'CommercialPagination'] as $component) {
            $this->assertStringContainsString("function $component", $source);
        }
        foreach (['aria-label="Sales sections"', 'aria-label="Sales results pages"', 'role="dialog"', 'prefers-reduced-motion'] as $contract) {
            $this->assertStringContainsString($contract, $source.$css);
        }
        foreach (['cw-commercial-hero', 'cw-commercial-summary', 'cw-commercial-filters', 'cw-commercial-table', 'cw-customer-card', 'cw-receipt-layout'] as $class) {
            $this->assertStringContainsString($class, $source);
            $this->assertStringContainsString('.'.$class, $css);
        }
        $this->assertStringContainsString('@media (max-width: 767px)', $css);
        $this->assertStringNotContainsString('Math.random', $source);
    }

    public function test_v18_php_files_parse(): void
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach ([
            'app/Http/Controllers/CommercialWorkspaceController.php',
            'app/Services/CommercialWorkspaceService.php',
            'app/Services/ReactWorkspaceContextService.php',
            'app/Http/Controllers/SellController.php',
            'app/Http/Controllers/ContactController.php',
            'app/Http/Controllers/BusinessDocumentController.php',
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
