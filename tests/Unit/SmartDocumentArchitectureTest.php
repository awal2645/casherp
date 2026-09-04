<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;

class SmartDocumentArchitectureTest extends TestCase
{
    public function test_all_six_industries_have_common_commercial_documents(): void
    {
        $config = require $this->path('config/smart_documents.php');
        $launchIndustries = [
            'general_business', 'restaurant_food_service', 'hotel_lodge_guesthouse',
            'hotel_with_restaurant', 'property_management_rentals', 'professional_services',
        ];
        foreach ($launchIndustries as $industry) {
            $this->assertArrayHasKey($industry, $config['industry_profiles']);
        }
        $this->assertGreaterThanOrEqual(13, count($config['industry_profiles']));

        $common = ['standard_quotation', 'standard_invoice', 'payment_receipt', 'credit_note'];
        foreach ($config['industry_profiles'] as $industry => $types) {
            foreach ($common as $type) {
                $this->assertContains($type, $types, "$industry is missing $type");
            }
        }
    }

    public function test_expanded_industries_have_specialist_document_chains(): void
    {
        $config = require $this->path('config/smart_documents.php');
        $expected = [
            'retail_wholesale' => ['sales_order', 'pos_receipt', 'delivery_note', 'goods_return_note'],
            'construction_contracting' => ['construction_estimate', 'construction_work_order', 'progress_invoice', 'completion_certificate'],
            'healthcare_clinic' => ['patient_estimate', 'patient_invoice', 'medical_payment_receipt', 'insurance_claim_summary'],
            'education_training' => ['enrollment_confirmation', 'tuition_invoice', 'fee_receipt'],
            'automotive_services' => ['vehicle_service_estimate', 'repair_work_order', 'vehicle_service_invoice'],
            'logistics_transport' => ['freight_quotation', 'consignment_note', 'freight_invoice', 'proof_of_delivery'],
            'nonprofit_membership' => ['donation_acknowledgement', 'donation_receipt', 'membership_invoice', 'membership_receipt'],
        ];
        foreach ($expected as $industry => $types) {
            foreach ($types as $type) {
                $this->assertContains($type, $config['industry_profiles'][$industry], "$industry is missing $type");
            }
        }

        $this->assertSame('receipt_80mm', $config['types']['pos_receipt']['output_format']);
    }

    public function test_every_document_profile_is_selectable_during_onboarding(): void
    {
        $documents = require $this->path('config/smart_documents.php');
        $onboarding = require $this->path('config/industry_onboarding.php');
        $migration = $this->read('database/migrations/2026_09_03_000015_expand_industry_document_library.php');

        foreach (array_keys($documents['industry_profiles']) as $industry) {
            $this->assertArrayHasKey($industry, $onboarding['profiles']);
        }
        foreach (['retail_wholesale', 'construction_contracting', 'healthcare_clinic', 'education_training', 'automotive_services', 'logistics_transport', 'nonprofit_membership'] as $industry) {
            $this->assertStringContainsString("'$industry'", $migration);
        }
        $this->assertStringContainsString("'hrm', 'smart_documents'", $migration);
    }

    public function test_property_hospitality_restaurant_and_services_have_correct_specialist_outputs(): void
    {
        $config = require $this->path('config/smart_documents.php');

        foreach (['rental_quotation', 'lease_agreement', 'rental_invoice', 'rent_receipt', 'security_deposit_receipt', 'owner_statement'] as $type) {
            $this->assertContains($type, $config['industry_profiles']['property_management_rentals']);
        }
        foreach (['room_booking_invoice', 'booking_payment_receipt', 'guest_folio', 'long_stay_agreement', 'event_reservation_contract', 'event_booking_invoice', 'catering_invoice'] as $type) {
            $this->assertContains($type, $config['industry_profiles']['hotel_lodge_guesthouse']);
            $this->assertContains($type, $config['industry_profiles']['hotel_with_restaurant']);
        }
        foreach (['catering_quotation', 'catering_invoice', 'event_reservation_contract'] as $type) {
            $this->assertContains($type, $config['industry_profiles']['restaurant_food_service']);
        }
        foreach (['service_proposal', 'service_contract', 'service_invoice', 'retainer_receipt'] as $type) {
            $this->assertContains($type, $config['industry_profiles']['professional_services']);
        }
    }

    public function test_agreements_require_acceptance_and_company_reviewed_terms(): void
    {
        $config = require $this->path('config/smart_documents.php');
        foreach (['lease_agreement', 'long_stay_agreement', 'event_reservation_contract', 'service_contract', 'construction_work_order', 'repair_work_order', 'proof_of_delivery'] as $type) {
            $this->assertTrue($config['types'][$type]['requires_acceptance']);
        }

        $service = $this->read('app/Services/BusinessDocumentService.php');
        $this->assertStringContainsString('terms_reviewed_at', $service);
        $this->assertStringContainsString('terms_reviewed_hash', $service);
        $this->assertStringContainsString('hash_equals', $service);
        $this->assertStringContainsString('A company administrator must review and approve the legal terms', $service);
        $this->assertStringContainsString('Only draft documents can be edited', $service);
    }

    public function test_source_resolver_is_company_scoped_and_supports_real_scenarios(): void
    {
        $source = $this->read('app/Services/DocumentSourceContextService.php');
        foreach (['transaction', 'property_lease', 'property_rent_due', 'property_rent_payment', 'hms_folio', 'business_document'] as $type) {
            $this->assertStringContainsString("'$type'", $source);
        }
        $this->assertStringContainsString("where('business_id', \$businessId)", $source);
        $this->assertStringContainsString("config('smart_documents.long_stay_nights', 28)", $source);
        $this->assertStringContainsString("'preferred_type_code' => 'lease_agreement'", $source);
        $this->assertStringContainsString("'preferred_type_code' => 'rent_receipt'", $source);
        $this->assertStringContainsString("'preferred_type_code' => 'guest_folio'", $source);

        $controller = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $this->assertStringContainsString('authorizeSourceAccess', $controller);
        $this->assertStringContainsString("\$user->canForBusiness('property.view'", $controller);
        $this->assertStringContainsString("\$user->can('hms.manage_folios')", $controller);
        $this->assertStringContainsString('TransactionDocumentAccessService::class', $controller);
        $this->assertStringContainsString('permitted_locations()', $controller);
    }

    public function test_numbering_sharing_and_workflow_are_controlled(): void
    {
        $service = $this->read('app/Services/BusinessDocumentService.php');
        $numbering = $this->read('app/Services/BusinessDocumentNumberService.php');
        $migration = $this->read('database/migrations/2026_09_03_000014_create_smart_document_system.php');
        $public = $this->read('app/Http/Controllers/PublicBusinessDocumentController.php');

        $this->assertStringContainsString("'document_number' => \$this->newDraftNumber", $service);
        $this->assertStringContainsString("\$toStatus === 'issued'", $service);
        $this->assertStringContainsString('lockForUpdate()', $numbering);
        $this->assertStringContainsString("hash('sha256', \$token)", $service);
        $this->assertStringContainsString('public_token_hash', $migration);
        $this->assertStringContainsString("where('public_token_hash', hash('sha256', \$token))", $public);
    }

    public function test_routes_and_admin_profile_controls_are_wired(): void
    {
        $routes = $this->read('routes/web.php');
        $superadminRoutes = $this->read('Modules/Superadmin/Routes/web.php');
        $controller = $this->read('app/Http/Controllers/Superadmin/IndustryDocumentProfileController.php');

        $this->assertStringContainsString("business.feature:smart_documents", $routes);
        $this->assertStringContainsString("Route::resource('smart-documents'", $routes);
        $this->assertStringContainsString("middleware('throttle:30,1')", $routes);
        $this->assertStringContainsString("'/industry-documents'", $superadminRoutes);
        $this->assertStringContainsString("auth()->user()->can('superadmin')", $controller);
        $this->assertStringContainsString("where('source', 'industry_default')", $controller);
    }

    public function test_document_access_is_action_type_and_company_scope_aware(): void
    {
        $access = $this->read('app/Services/BusinessDocumentAccessService.php');
        $controller = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $migration = $this->read('database/migrations/2026_09_03_000014_create_smart_document_system.php');
        $settings = $this->read('resources/views/smart_documents/settings.blade.php');

        $this->assertStringContainsString("'smart_documents.type.'.\$type->code", $access);
        $this->assertStringContainsString('hrm_employment_profiles', $access);
        $this->assertStringContainsString("data_get(optional(\$setting)->settings, 'access'", $access);
        $this->assertStringContainsString('permittedTypes', $controller);
        $this->assertStringContainsString("'smart_documents.type.'.\$typeCode", $migration);
        $this->assertStringContainsString('allowed_role_ids', $settings);
        $this->assertStringContainsString('allowed_department_ids', $settings);
        $this->assertStringContainsString('allowed_user_ids', $settings);
    }

    public function test_pos_receipt_has_a_dedicated_print_format(): void
    {
        $pdf = $this->read('app/Services/BusinessDocumentPdfService.php');
        $this->assertStringContainsString("'smart_documents.print_receipt'", $pdf);
        $this->assertStringContainsString("[80, 297]", $pdf);
    }

    public function test_post_issue_payments_are_ledgered_and_receipted_safely(): void
    {
        $service = $this->read('app/Services/BusinessDocumentService.php');
        $source = $this->read('app/Services/DocumentSourceContextService.php');
        $routes = $this->read('routes/web.php');
        $migration = $this->read('database/migrations/2026_09_03_000016_create_business_document_payments.php');

        $this->assertStringContainsString('function recordPayment', $service);
        $this->assertStringContainsString('function reversePayment', $service);
        $this->assertStringContainsString('cannot exceed the current document balance', $service);
        $this->assertStringContainsString('Void the receipt covering this payment', $service);
        $this->assertStringContainsString('exceeds the source document payment', $service);
        $this->assertStringContainsString('receiptedAmount', $source);
        $this->assertStringContainsString("'smart_documents.payment.create'", $migration);
        $this->assertStringContainsString("'smart_documents.payment.reverse'", $migration);
        $this->assertStringContainsString("->name('smart-documents.payments.store')", $routes);
        $this->assertStringContainsString("->name('smart-documents.payments.reverse')", $routes);
    }

    public function test_smart_document_blade_templates_compile_to_valid_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        foreach ([
            'resources/views/smart_documents/index.blade.php',
            'resources/views/smart_documents/form.blade.php',
            'resources/views/smart_documents/show.blade.php',
            'resources/views/smart_documents/settings.blade.php',
            'resources/views/smart_documents/print.blade.php',
            'resources/views/smart_documents/print_receipt.blade.php',
            'resources/views/smart_documents/public.blade.php',
            'resources/views/role/create.blade.php',
            'resources/views/role/edit.blade.php',
            'Modules/Superadmin/Resources/views/industry_documents/index.blade.php',
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
