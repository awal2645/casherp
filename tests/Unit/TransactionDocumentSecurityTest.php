<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class TransactionDocumentSecurityTest extends TestCase
{
    public function test_smart_documents_have_distinct_preview_print_download_and_secure_share_paths(): void
    {
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $public = $this->read('app/Http/Controllers/PublicBusinessDocumentController.php');
        $view = $this->read('resources/views/smart_documents/show.blade.php');

        foreach (['smart-documents.preview', 'smart-documents.print', 'smart-documents.download'] as $name) {
            $this->assertStringContainsString("->name('$name')", $routes);
        }
        foreach (['public.smart-document.show', 'public.smart-document.print', 'public.smart-document.download'] as $name) {
            $this->assertStringContainsString("->name('$name')", $routes);
        }
        $this->assertStringContainsString("'attachment', 'download'", $controller);
        $this->assertStringContainsString("whereIn('status', ['issued', 'sent', 'accepted', 'completed'])", $public);
        $this->assertStringContainsString("hash('sha256', \$token)", $public);
        $this->assertStringContainsString('Create link', $view);
        $this->assertStringContainsString('Revoke current link', $view);
    }

    public function test_native_sales_documents_use_one_company_location_role_and_lifecycle_boundary(): void
    {
        $access = $this->read('app/Services/TransactionDocumentAccessService.php');
        $sell = $this->read('app/Http/Controllers/SellController.php');
        $pos = $this->read('app/Http/Controllers/SellPosController.php');
        $util = $this->read('app/Utils/Util.php');

        $this->assertStringContainsString("Transaction::where('business_id', \$businessId)->findOrFail", $access);
        $this->assertStringContainsString('locationAllowed', $access);
        $this->assertStringContainsString("canForBusiness('sell.document.share'", $access);
        $this->assertStringContainsString("canForBusiness('print_invoice'", $access);
        $this->assertStringContainsString('authorizeEdit', $access);
        $this->assertStringContainsString("->resolve((int) \$business_id, (int) \$id", $pos);
        $this->assertStringContainsString("'Content-Disposition' => 'attachment; filename=", $pos);
        $this->assertStringContainsString("->header('X-Robots-Tag', 'noindex, nofollow, noarchive')", $pos);
        $this->assertStringContainsString('TransactionDocumentAccessService::class', $sell);
        $this->assertStringContainsString('Str::random(64)', $util);
    }

    public function test_property_access_is_company_location_property_and_ability_scoped(): void
    {
        $access = $this->read('app/Services/PropertyAccessService.php');
        $controller = $this->read('app/Http/Controllers/PropertyManagementController.php');
        $dashboard = $this->read('app/Services/PropertyDashboardService.php');
        $viewings = $this->read('app/Http/Controllers/PropertyViewingController.php');
        $documents = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $migration = $this->read('database/migrations/2026_09_03_000026_create_transaction_document_and_property_access_controls.php');

        foreach (['view', 'manage', 'rent', 'maintenance', 'viewings', 'documents', 'accounting'] as $ability) {
            $this->assertStringContainsString("'$ability' =>", $access);
        }
        $this->assertStringContainsString("where('properties.business_id', \$businessId)", $access);
        $this->assertStringContainsString("whereJsonContains('pag.abilities', \$ability)", $access);
        $this->assertStringContainsString('scopeBusinessDocuments', $documents);
        $this->assertStringContainsString("'business_document'", $access);
        $this->assertStringContainsString("ensureDueAccessible(\$due, 'rent')", $controller);
        $this->assertStringContainsString("ensureMaintenanceAccessible(\$ticket, 'maintenance')", $controller);
        $this->assertStringContainsString('PropertyAccessService::class', $dashboard);
        $this->assertStringContainsString("'viewings'", $viewings);
        $this->assertStringContainsString("Schema::create('property_access_grants'", $migration);
        $this->assertStringContainsString("'property.access.manage'", $migration);
    }

    public function test_purchase_documents_are_authorized_and_have_controlled_outputs(): void
    {
        $routes = $this->read('routes/web.php');
        $purchases = $this->read('app/Http/Controllers/PurchaseController.php');
        $orders = $this->read('app/Http/Controllers/PurchaseOrderController.php');
        $requisitions = $this->read('app/Http/Controllers/PurchaseRequisitionController.php');
        $notifications = $this->read('app/Http/Controllers/NotificationController.php');

        $this->assertStringContainsString("->where('type', 'purchase')", $purchases);
        $this->assertStringContainsString('authorizePurchaseDocument', $purchases);
        $this->assertStringContainsString('assertLocationAccess', $orders);
        $this->assertStringContainsString("\$mpdf->Output(\$safePdfName, 'D')", $orders);
        $this->assertStringContainsString("->name('purchase-requisition.print')", $routes);
        $this->assertStringContainsString("->name('purchase-requisition.download')", $routes);
        $this->assertStringContainsString('authorizedRequisition', $requisitions);
        $this->assertStringContainsString("'Content-Disposition' => \$disposition", $requisitions);
        $this->assertStringContainsString("canForBusiness('purchase.document.share'", $notifications);
        $this->assertStringContainsString("\$workflowStatus !== 'approved'", $notifications);
    }

    public function test_hotel_checklist_features_are_present_without_combining_hms_and_hrm(): void
    {
        $frontDesk = $this->read('Modules/Hms/Http/Controllers/FrontDeskController.php');
        $frontDeskView = $this->read('Modules/Hms/Resources/views/front_desk/index.blade.php');
        $housekeeping = $this->read('Modules/Hms/Services/HousekeepingService.php');
        $lifecycle = $this->read('Modules/Hms/Services/BookingLifecycleService.php');
        $ratePlans = $this->read('Modules/Hms/Services/RatePlanService.php');
        $routes = $this->read('Modules/Hms/Routes/web.php');
        $onboarding = $this->read('config/industry_onboarding.php');

        $this->assertStringContainsString('arrivals', $frontDesk);
        $this->assertStringContainsString('departures', $frontDesk);
        $this->assertStringContainsString('overdue_departures', $frontDeskView);
        $this->assertStringContainsString('createCheckoutTasks', $lifecycle);
        $this->assertStringContainsString("'housekeeping_status' => \$passed ? 'ready'", $housekeeping);
        $this->assertStringContainsString('assertAllotment', $ratePlans);
        $this->assertStringContainsString("'/groups'", $routes);
        $this->assertStringContainsString('a separate Human Resource Management (HRM) workspace', $onboarding);
    }

    public function test_changed_blade_templates_compile_to_valid_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        foreach ([
            'resources/views/smart_documents/index.blade.php',
            'resources/views/smart_documents/show.blade.php',
            'resources/views/smart_documents/public.blade.php',
            'resources/views/sale_pos/partials/invoice_url_modal.blade.php',
            'resources/views/sale_pos/partials/show_invoice.blade.php',
            'resources/views/property/access.blade.php',
            'resources/views/property/dashboard.blade.php',
            'resources/views/purchase_order/show.blade.php',
            'resources/views/purchase_requisition/show.blade.php',
            'resources/views/purchase_requisition/pdf.blade.php',
            'resources/views/role/create.blade.php',
            'resources/views/role/edit.blade.php',
        ] as $view) {
            $compiled = $compiler->compileString($this->read($view));
            $this->assertNotEmpty($parser->parse($compiled), "$view did not compile to parseable PHP");
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
