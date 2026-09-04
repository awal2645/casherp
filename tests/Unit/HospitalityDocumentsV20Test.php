<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\View\Compilers\BladeCompiler;
use Modules\Hms\Services\HospitalityDocumentService;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class HospitalityDocumentsV20Test extends TestCase
{
    public function test_accommodation_uses_calendar_nights_and_never_puts_payments_on_a_quotation(): void
    {
        require_once $this->path('Modules/Hms/Services/HospitalityDocumentService.php');
        $booking = (object) [
            'transaction_date' => '2026-06-01 09:30:00',
            'hms_booking_arrival_date_time' => '2026-06-10 14:00:00',
            'hms_booking_departure_date_time' => '2026-06-14 10:00:00',
            'created_at' => null,
        ];
        $context = (new HospitalityDocumentService())->stayContext($booking, 'quotation');

        $this->assertSame('Accommodation Quotation', $context['title']);
        $this->assertSame('2026-06-01', $context['booking_date']);
        $this->assertSame('2026-06-10', $context['arrival_date']);
        $this->assertSame('14:00', $context['arrival_time']);
        $this->assertSame('2026-06-14', $context['checkout_date']);
        $this->assertSame('10:00', $context['checkout_time']);
        $this->assertSame(4, $context['number_of_days']);
        $this->assertFalse($context['show_payment_summary']);
        $this->assertFalse($context['show_payment_lines']);
    }

    public function test_security_deposit_payments_are_excluded_from_accommodation_payment_totals(): void
    {
        require_once $this->path('Modules/Hms/Services/HospitalityDocumentService.php');
        $payments = collect([
            (object) ['amount' => 500, 'payment_purpose' => 'payment_deposit'],
            (object) ['amount' => 200, 'payment_purpose' => 'security_deposit'],
            (object) ['amount' => 100, 'payment_purpose' => null],
        ]);
        $filtered = (new HospitalityDocumentService())->accommodationPayments($payments);

        $this->assertSame(2, $filtered->count());
        $this->assertSame(600.0, (float) $filtered->sum('amount'));
        $this->assertFalse($filtered->contains(fn ($payment) => $payment->payment_purpose === 'security_deposit'));
    }

    public function test_hospitality_document_families_are_distinct_and_use_correct_formats(): void
    {
        $config = require $this->path('config/smart_documents.php');
        foreach (['booking_date', 'arrival_date', 'arrival_time', 'checkout_date', 'checkout_time', 'number_of_days', 'stay_duration'] as $key) {
            $this->assertContains($key, collect($config['schema_fields']['stay'])->pluck('key'));
        }
        foreach (['room_booking_quotation', 'room_booking_invoice', 'booking_payment_receipt'] as $code) {
            $this->assertSame('a4', $config['types'][$code]['output_format']);
        }
        $this->assertSame('a4', $config['types']['event_booking_invoice']['output_format']);
        $this->assertSame('a4', $config['types']['event_payment_receipt']['output_format']);
        $this->assertSame('receipt_80mm', $config['types']['pos_receipt']['output_format']);
        foreach (['restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant', 'property_management_rentals'] as $industry) {
            $this->assertContains('event_payment_receipt', $config['industry_profiles'][$industry]);
        }
    }

    public function test_refundable_extras_are_snapshotted_and_kept_outside_hotel_revenue(): void
    {
        $migration = $this->read('Modules/Hms/Database/Migrations/2026_09_04_000007_separate_refundable_booking_extras.php');
        $integrity = $this->read('Modules/Hms/Services/BookingIntegrityService.php');
        $folio = $this->read('Modules/Hms/Services/FolioService.php');
        $documentSource = $this->read('app/Services/DocumentSourceContextService.php');
        $booking = $this->read('Modules/Hms/Http/Controllers/HmsBookingController.php');

        $this->assertStringContainsString("financial_classification', 40", $migration);
        $this->assertStringContainsString("'refundable_security_deposit'", $migration);
        $this->assertStringContainsString("where('financial_classification', 'revenue')", $integrity.$folio);
        $this->assertStringContainsString("'security_deposit_required'", $integrity);
        $this->assertStringContainsString('SecurityDepositService::class)->forHmsFolio', $booking);
        $this->assertStringContainsString("orWhere('payment_purpose', '!=', 'security_deposit')", $folio);
        $this->assertStringContainsString("entry->category === 'security_deposit'", $documentSource);
        $this->assertStringContainsString("'security_deposit' => optional(\$securityDeposit)->required_amount", $documentSource);
    }

    public function test_event_sources_use_standard_a4_documents_and_fixed_financial_directions(): void
    {
        $source = $this->read('app/Services/DocumentSourceContextService.php');
        $request = $this->read('app/Http/Requests/StoreBusinessDocumentRequest.php');
        $events = $this->read('Modules/Hms/Services/EventBookingService.php');
        $eventUi = $this->read('Modules/Hms/Resources/views/events/index.blade.php');

        $this->assertStringContainsString("'hms_event_booking'", $source.$request);
        $this->assertStringContainsString("'preferred_type_code' => 'event_booking_invoice'", $source);
        $this->assertStringContainsString("'asset_type' => 'hms_event_venue'", $source);
        $this->assertStringContainsString("['payment_deposit', 'discount']", $events);
        $this->assertStringContainsString("'event_payment_receipt'", $source);
        $this->assertStringContainsString('A4 quotation', $eventUi);
        $this->assertStringContainsString('A4 invoice', $eventUi);
        $this->assertStringNotContainsString("Form::select('direction'", $eventUi);
    }

    public function test_hotel_restaurant_sales_expose_a4_and_pos_outputs_in_react(): void
    {
        $service = $this->read('app/Services/CommercialWorkspaceService.php');
        $react = $this->read('resources/js/casherp-workspaces-react.jsx');
        $source = $this->read('app/Services/DocumentSourceContextService.php');

        foreach (['restaurant_food_service', 'hotel_with_restaurant'] as $industry) {
            $this->assertStringContainsString("'$industry'", $service);
        }
        $this->assertStringContainsString("'a4_invoice'", $service);
        $this->assertStringContainsString("'pos_receipt'", $service);
        $this->assertStringContainsString('Create A4 invoice', $react);
        $this->assertStringContainsString('Create 80mm POS receipt', $react);
        $this->assertStringContainsString("'sell_line_id'", $source);
    }

    public function test_nonfinancial_documents_cannot_store_or_print_a_payment_section(): void
    {
        $service = $this->read('app/Services/BusinessDocumentService.php');
        $form = $this->read('resources/views/smart_documents/form.blade.php');
        $print = $this->read('resources/views/smart_documents/print.blade.php');

        $this->assertStringContainsString('$type->is_financial', $service);
        $this->assertStringContainsString("meta.code === 'room_booking_quotation'", $form);
        $this->assertStringContainsString('paymentGroup.style.display', $form);
        $this->assertStringContainsString('$showPayments', $print);
        $this->assertStringContainsString("'room_booking_quotation'", $print);
        $this->assertStringContainsString('Quoted total', $print);
    }

    public function test_international_controls_keep_policy_configurable_and_card_data_minimal(): void
    {
        $guide = $this->read('resources/views/partials/advance_deposit_guidance.blade.php');
        $booking = $this->read('Modules/Hms/Http/Controllers/HmsBookingController.php');
        $architecture = $this->read('docs/HOSPITALITY_DOCUMENT_ARCHITECTURE_V20.md');

        $this->assertStringContainsString('advisory only', $guide);
        $this->assertStringContainsString('You may still continue.', $guide);
        $this->assertStringNotContainsString('international rule', strtolower($guide));
        $this->assertStringContainsString("\$line['card_security'] = null", $booking);
        $this->assertStringContainsString("'****' . substr(\$digits, -4)", $booking);
        $this->assertStringContainsString('ISO 4217', $architecture);
        $this->assertStringContainsString('PCI DSS', $architecture);
        $this->assertStringContainsString('jurisdiction configurable', $architecture);
    }

    public function test_changed_hospitality_blade_templates_compile_to_parseable_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        foreach ([
            'resources/views/smart_documents/form.blade.php',
            'resources/views/smart_documents/print.blade.php',
            'Modules/Hms/Resources/views/bookings/create.blade.php',
            'Modules/Hms/Resources/views/bookings/edit.blade.php',
            'Modules/Hms/Resources/views/bookings/show.blade.php',
            'Modules/Hms/Resources/views/bookings/accommodation_a4.blade.php',
            'Modules/Hms/Resources/views/bookings/receipt_80mm.blade.php',
            'Modules/Hms/Resources/views/events/index.blade.php',
            'Modules/Hms/Resources/views/extras/create.blade.php',
            'Modules/Hms/Resources/views/extras/edit.blade.php',
        ] as $view) {
            $compiled = $compiler->compileString($this->read($view));
            $this->assertNotEmpty($parser->parse($compiled), $view.' did not compile to parseable PHP');
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
