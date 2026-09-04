<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class SecurityDepositArchitectureTest extends TestCase
{
    public function test_security_and_payment_deposits_are_separate_ledgers(): void
    {
        $migration = $this->read('database/migrations/2026_09_03_000027_create_security_deposit_and_damage_ledgers.php');
        $security = $this->read('app/Services/SecurityDepositService.php');
        $advance = $this->read('app/Services/PropertyPaymentDepositService.php');

        $this->assertStringContainsString("Schema::create('security_deposits'", $migration);
        $this->assertStringContainsString("Schema::create('property_payment_deposits'", $migration);
        $this->assertStringContainsString("'payment_purpose'", $migration);
        $this->assertStringContainsString('intentionally isolated from invoices', $security);
        $this->assertStringNotContainsString('AccountingAccountsTransaction', $security);
        $this->assertStringContainsString('PropertyAccountingPostingService', $advance);
        $this->assertStringContainsString('PropertyPaymentDeposit::create', $advance);
        $this->assertStringContainsString('postPaymentDepositReceipt', $advance);
        $this->assertStringContainsString('postPaymentDepositApplication', $advance);
        $posting = $this->read('app/Services/PropertyAccountingPostingService.php');
        $this->assertStringContainsString('payment_advance_liability_account_id', $posting);
        $this->assertStringContainsString("'payment_deposit_receipt'", $posting);
        $this->assertStringContainsString("'payment_deposit_allocation'", $posting);
    }

    public function test_all_risk_deposits_require_a_real_business_context(): void
    {
        $request = $this->read('app/Http/Requests/StoreBusinessDocumentRequest.php');
        $security = $this->read('app/Services/SecurityDepositService.php');
        $form = $this->read('resources/views/smart_documents/form.blade.php');

        $this->assertStringContainsString('required_if:scenario_code,event', $request);
        $this->assertStringContainsString("Rule::in(['property_unit', 'hms_event_venue', 'business_location'])", $request);
        $this->assertStringContainsString('Select the actual property unit, hotel event venue, or responsible operating location', $security);
        $this->assertStringContainsString("'context_type' => 'property_lease'", $security);
        $this->assertStringContainsString("'context_type' => 'hms_booking'", $security);
        $this->assertStringContainsString("'context_type' => 'event_document'", $security);
        $this->assertStringContainsString('Select the exact asset or venue', $form);
    }

    public function test_refundable_deposits_block_closure_until_settled_or_waived(): void
    {
        $security = $this->read('app/Services/SecurityDepositService.php');
        $property = $this->read('app/Http/Controllers/PropertyManagementController.php');
        $hotel = $this->read('Modules/Hms/Services/BookingLifecycleService.php');
        $documents = $this->read('app/Http/Controllers/BusinessDocumentController.php');

        $this->assertStringContainsString('assertContextCanClose', $security);
        $this->assertStringContainsString("['settled', 'waived']", $security);
        $this->assertStringContainsString('assertContextCanClose($businessId, \'property_lease\'', $property);
        $this->assertStringContainsString('assertContextCanClose($businessId, \'hms_booking\'', $hotel);
        $this->assertStringContainsString('assertEventDocumentCanClose($scoped)', $documents);
    }

    public function test_hotel_event_documents_share_one_deposit_and_cannot_change_source_identity(): void
    {
        $security = $this->read('app/Services/SecurityDepositService.php');
        $documents = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $documentService = $this->read('app/Services/BusinessDocumentService.php');
        $request = $this->read('app/Http/Requests/StoreBusinessDocumentRequest.php');

        $this->assertStringContainsString('eventDocumentContext', $security);
        $this->assertStringContainsString("\$document->source_type === 'hms_event_booking'", $security);
        $this->assertStringContainsString("'context_type' => 'hms_event'", $security);
        $this->assertStringContainsString('findForEventDocument($document)', $documents);
        $this->assertStringContainsString('scopedEventDepositForEntry', $documents);
        $this->assertStringContainsString("whereIn('context_type', ['event_document', 'hms_event'])", $documents);
        $this->assertStringContainsString("'hms_event_booking' => 'hms_event_bookings'", $documentService);
        $this->assertStringContainsString('assertImmutableSource($document, $input)', $documentService);
        $this->assertStringContainsString('A document source and its parent link cannot be changed', $documentService);
        $this->assertStringContainsString('The document venue must match the selected hotel event booking.', $documents);
        $this->assertStringContainsString('The document customer must match the selected hotel event booking.', $documents);
        $this->assertStringContainsString('The document event dates must match the selected hotel event booking.', $documents);
        $this->assertStringContainsString("'service_start_at' => ['nullable', 'required_if:scenario_code,event'", $request);
        $this->assertStringContainsString("'service_end_at' => ['nullable', 'required_if:scenario_code,event'", $request);
    }

    public function test_damage_creates_a_billable_audit_trail_without_reclassifying_held_funds(): void
    {
        $config = require $this->path('config/smart_documents.php');
        $damage = $this->read('app/Services/DamageChargeDocumentService.php');
        $hotel = $this->read('Modules/Hms/Http/Controllers/FolioController.php');
        $security = $this->read('app/Services/SecurityDepositService.php');

        $this->assertFalse($config['types']['security_deposit_receipt']['is_financial']);
        $this->assertSame('liability', $config['types']['security_deposit_receipt']['category']);
        $this->assertTrue($config['types']['damage_charge_invoice']['is_financial']);
        foreach (['property_management_rentals', 'hotel_lodge_guesthouse', 'hotel_with_restaurant'] as $industry) {
            $this->assertContains('damage_charge_invoice', $config['industry_profiles'][$industry]);
        }
        $this->assertStringContainsString('linked financial document', $damage);
        $this->assertStringContainsString("'amount_paid' => \$retainedAmount", $damage);
        $this->assertStringContainsString("'security_deposit_applied'", $hotel);
        $this->assertStringContainsString('excessDamageIsResolved', $security);
    }

    public function test_refund_approval_and_damage_billing_have_fail_closed_controls(): void
    {
        $security = $this->read('app/Services/SecurityDepositService.php');
        $damage = $this->read('app/Services/DamageChargeDocumentService.php');
        $routes = $this->read('routes/web.php');
        $hotelRoutes = $this->read('Modules/Hms/Routes/web.php');

        $this->assertStringContainsString('(int) $locked->created_by === $actorId', $security);
        $this->assertStringContainsString('A different authorized user must review and approve', $security);
        $this->assertStringContainsString("'status' => 'approved'", $security);
        $this->assertStringContainsString('public function payRefund', $security);
        $this->assertStringContainsString("'paid_by' => \$actorId", $security);
        $this->assertStringContainsString('payEventSecurityDepositRefund', $routes);
        $this->assertStringContainsString('PropertyDepositController::class, \'payRefund\'', $routes);
        $this->assertStringContainsString('paySecurityDepositRefund', $hotelRoutes);
        $this->assertStringContainsString('Enable Damage Charge Invoice', $damage);
        $this->assertStringContainsString('ValidationException::withMessages', $damage);
        $this->assertStringNotContainsString('private function create(Business $business, SecurityDepositEntry $entry, array $context, int $actorId): ?BusinessDocument', $damage);
    }

    public function test_company_payment_methods_are_customizable_but_non_money_terms_cannot_post_receipts(): void
    {
        $source = $this->read('app/Services/TenantPaymentMethodService.php');
        $settings = $this->read('resources/views/smart_documents/settings.blade.php');

        foreach (['cash', 'card', 'mobile_money', 'bank_transfer', 'credit', 'complimentary', 'custom_1', 'custom_5'] as $code) {
            $this->assertStringContainsString("'$code' =>", $source);
        }
        $this->assertStringContainsString("'accepts_money' => false", $source);
        $this->assertStringContainsString('Credit and complimentary are payment terms, not receipts', $source);
        $this->assertStringContainsString('payment_methods[', $settings);
        $this->assertStringContainsString('apply only to this company', $settings);
    }

    public function test_hospitality_and_property_forms_show_a_non_blocking_fifty_percent_advance_guide(): void
    {
        $guide = $this->read('resources/views/partials/advance_deposit_guidance.blade.php');
        $documents = $this->read('resources/views/smart_documents/form.blade.php');
        $controller = $this->read('app/Http/Controllers/BusinessDocumentController.php');
        $hotelCreate = $this->read('Modules/Hms/Resources/views/bookings/create.blade.php');
        $hotelEdit = $this->read('Modules/Hms/Resources/views/bookings/edit.blade.php');
        $publicBooking = $this->read('Modules/Hms/Resources/views/public/booking.blade.php');
        $propertyLease = $this->read('resources/views/property/create_lease.blade.php');

        $this->assertStringContainsString('50% Payment Deposit (Advance) guide', $guide);
        $this->assertStringContainsString('advisory only', $guide);
        $this->assertStringContainsString('does not block saving, invoicing, or reservation', $guide);
        $this->assertStringContainsString('Do not include a Security Deposit (Refundable)', $guide);
        $this->assertStringContainsString('refreshAdvanceDepositGuides', $guide);
        $this->assertStringContainsString("'industryCode' => optional(\$business->industry)->code", $controller);
        foreach (['property_management_rentals', 'hotel_lodge_guesthouse', 'hotel_with_restaurant'] as $industry) {
            $this->assertStringContainsString($industry, $documents);
        }
        $this->assertStringContainsString('hms-create-booking-advance-guide', $hotelCreate);
        $this->assertStringContainsString('hms-edit-booking-advance-guide', $hotelEdit);
        $this->assertStringContainsString('You may submit this availability request without payment', $publicBooking);
        $this->assertStringContainsString('property-lease-advance-guide', $propertyLease);
    }

    public function test_deposit_views_compile_to_parseable_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        foreach ([
            'resources/views/security_deposits/panel.blade.php',
            'resources/views/partials/advance_deposit_guidance.blade.php',
            'resources/views/property/deposits/index.blade.php',
            'resources/views/property/deposits/show.blade.php',
            'resources/views/property/create_lease.blade.php',
            'resources/views/smart_documents/index.blade.php',
            'resources/views/smart_documents/show.blade.php',
            'resources/views/smart_documents/settings.blade.php',
            'resources/views/smart_documents/form.blade.php',
            'Modules/Hms/Resources/views/bookings/create.blade.php',
            'Modules/Hms/Resources/views/bookings/edit.blade.php',
            'Modules/Hms/Resources/views/public/booking.blade.php',
            'Modules/Hms/Resources/views/groups/index.blade.php',
            'Modules/Hms/Resources/views/rate_plans/index.blade.php',
            'Modules/Hms/Resources/views/folios/index.blade.php',
            'Modules/Hms/Resources/views/folios/show.blade.php',
            'Modules/Hms/Resources/views/event_venues/index.blade.php',
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
