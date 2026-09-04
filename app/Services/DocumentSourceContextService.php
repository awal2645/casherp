<?php

namespace App\Services;

use App\BusinessDocument;
use App\PropertyLease;
use App\PropertyRentDue;
use App\PropertyRentPayment;
use App\SecurityDeposit;
use App\Transaction;
use Carbon\Carbon;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsTransactionClass;

class DocumentSourceContextService
{
    private const TYPES = ['transaction', 'property_lease', 'property_rent_due', 'property_rent_payment', 'hms_folio', 'hms_event_booking', 'business_document'];

    public function resolve(int $businessId, ?string $sourceType, ?int $sourceId): array
    {
        if (empty($sourceType) || empty($sourceId)) {
            return [];
        }
        abort_unless(in_array($sourceType, self::TYPES, true), 422, 'Unsupported document source.');

        if ($sourceType === 'property_lease') {
            return $this->propertyLease($businessId, $sourceId);
        }
        if ($sourceType === 'property_rent_due') {
            return $this->propertyRentDue($businessId, $sourceId);
        }
        if ($sourceType === 'property_rent_payment') {
            return $this->propertyRentPayment($businessId, $sourceId);
        }
        if ($sourceType === 'hms_folio') {
            return $this->hmsFolio($businessId, $sourceId);
        }
        if ($sourceType === 'hms_event_booking') {
            return $this->hmsEventBooking($businessId, $sourceId);
        }
        if ($sourceType === 'business_document') {
            return $this->businessDocument($businessId, $sourceId);
        }

        return $this->transaction($businessId, $sourceId);
    }

    private function propertyLease(int $businessId, int $id): array
    {
        $lease = PropertyLease::where('business_id', $businessId)
            ->with(['unit.property', 'tenant'])
            ->findOrFail($id);
        $months = $lease->end_date
            ? max(1, $lease->start_date->copy()->startOfDay()->diffInMonths($lease->end_date->copy()->startOfDay()))
            : 1;
        $property = optional($lease->unit)->property;

        return [
            'scenario_code' => 'rental',
            'preferred_type_code' => 'lease_agreement',
            'contact_id' => $lease->contact_id,
            'service_start_at' => optional($lease->start_date)->toDateString(),
            'service_end_at' => optional($lease->end_date)->toDateString(),
            'data' => [
                'property_name' => optional($property)->name,
                'unit_reference' => optional($lease->unit)->unit_code,
                'property_address' => optional($property)->address,
                'lease_start' => optional($lease->start_date)->toDateString(),
                'lease_end' => optional($lease->end_date)->toDateString(),
                'payment_frequency' => 'monthly',
                'rent_amount' => $lease->monthly_rent,
                'contract_total' => round((float) $lease->monthly_rent * $months, 4),
                'security_deposit' => $lease->security_deposit,
            ],
            'lines' => [[
                'description' => 'Rental of '.trim(optional($property)->name.' '.optional($lease->unit)->unit_code),
                'quantity' => $months,
                'unit_price' => $lease->monthly_rent,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
        ];
    }

    private function propertyRentDue(int $businessId, int $id): array
    {
        $due = PropertyRentDue::where('business_id', $businessId)
            ->with(['lease.unit.property', 'lease.tenant'])
            ->findOrFail($id);
        $property = optional(optional($due->lease)->unit)->property;

        return [
            'scenario_code' => 'rental',
            'preferred_type_code' => 'rental_invoice',
            'contact_id' => optional($due->lease)->contact_id,
            'issue_date' => optional($due->due_date)->toDateString(),
            'data' => [
                'property_name' => optional($property)->name,
                'unit_reference' => optional(optional($due->lease)->unit)->unit_code,
                'property_address' => optional($property)->address,
                'lease_start' => optional(optional($due->lease)->start_date)->toDateString(),
                'lease_end' => optional(optional($due->lease)->end_date)->toDateString(),
                'payment_frequency' => 'monthly',
                'rent_amount' => $due->amount_due,
            ],
            'amount_paid' => $due->amount_paid,
            'lines' => [[
                'description' => 'Rent due for '.optional($due->due_date)->format('F Y'),
                'quantity' => 1,
                'unit_price' => $due->amount_due,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
        ];
    }

    private function propertyRentPayment(int $businessId, int $id): array
    {
        $payment = PropertyRentPayment::where('business_id', $businessId)
            ->with('due.lease.unit.property')
            ->findOrFail($id);
        $due = $payment->due;
        $property = optional(optional(optional($due)->lease)->unit)->property;

        return [
            'scenario_code' => 'payment',
            'preferred_type_code' => 'rent_receipt',
            'contact_id' => optional(optional($due)->lease)->contact_id,
            'issue_date' => optional($payment->paid_on)->toDateString(),
            'data' => [
                'property_name' => optional($property)->name,
                'unit_reference' => optional(optional(optional($due)->lease)->unit)->unit_code,
                'rental_period' => optional(optional($due)->due_date)->format('F Y'),
                'payment_date' => optional($payment->paid_on)->toDateString(),
                'payment_method' => ucfirst(str_replace('_', ' ', $payment->method)),
                'payment_reference' => $payment->reference,
                'deposit_type' => 'not_applicable',
            ],
            'amount_paid' => $payment->amount,
            'lines' => [[
                'description' => 'Rent payment for '.optional(optional($due)->due_date)->format('F Y'),
                'quantity' => 1,
                'unit_price' => $payment->amount,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
        ];
    }

    private function transaction(int $businessId, int $id): array
    {
        $transaction = Transaction::where('business_id', $businessId)
            ->with(['contact', 'location', 'payment_lines', 'sell_lines.product', 'sell_lines.variations'])
            ->findOrFail($id);
        $isHms = $transaction->type === 'hms_booking';
        if ($isHms) {
            $transaction = HmsTransactionClass::where('business_id', $businessId)
                ->with(['contact', 'location', 'payment_lines', 'hms_booking_lines.room', 'hms_booking_extras.extra', 'hms_rate_plan'])
                ->findOrFail($id);
        }
        $commercialPayments = $transaction->payment_lines
            ->reject(fn ($payment) => ($payment->payment_purpose ?? null) === 'security_deposit');
        $lastPayment = $commercialPayments->where('is_return', false)->sortByDesc('paid_on')->first();
        $amountPaid = max(0, round(
            (float) $commercialPayments->where('is_return', false)->sum('amount')
            - (float) $commercialPayments->where('is_return', true)->sum('amount'),
            4
        ));
        $scenario = 'sale';
        $preferred = $transaction->status === 'draft' && ! empty($transaction->is_quotation)
            ? 'standard_quotation'
            : 'standard_invoice';
        $data = [
            'customer_reference' => $transaction->ref_no,
            'invoice_reference' => $transaction->invoice_no ?: $transaction->ref_no,
            'payment_date' => optional($lastPayment)->paid_on
                ? Carbon::parse($lastPayment->paid_on)->toDateString()
                : now()->toDateString(),
            'payment_method' => optional($lastPayment)->method
                ? ucfirst(str_replace('_', ' ', $lastPayment->method))
                : null,
            'payment_reference' => optional($lastPayment)->payment_ref_no,
        ];
        $description = 'Invoice '.$transaction->invoice_no;
        $documentLines = [];

        if ($isHms) {
            $arrival = Carbon::parse($transaction->hms_booking_arrival_date_time);
            $departure = Carbon::parse($transaction->hms_booking_departure_date_time);
            $nights = max(1, $arrival->copy()->startOfDay()->diffInDays($departure->copy()->startOfDay()));
            $scenario = $nights >= (int) config('smart_documents.long_stay_nights', 28) ? 'long_stay' : 'short_stay';
            $preferred = $scenario === 'long_stay'
                ? 'long_stay_agreement'
                : ($transaction->status === 'draft' && ! empty($transaction->is_quotation)
                    ? 'room_booking_quotation'
                    : 'room_booking_invoice');
            $description = 'Accommodation booking '.$transaction->ref_no;
            $roomSummary = $transaction->hms_booking_lines->map(function ($line) {
                return trim('Room '.optional($line->room)->room_number.' · '.((int) $line->adults).' adult(s) · '.((int) $line->childrens).' child(ren)');
            })->filter()->join("\n");
            $revenueExtras = $transaction->hms_booking_extras
                ->filter(fn ($line) => ($line->financial_classification ?? 'revenue') === 'revenue')
                ->map(fn ($line) => trim(optional($line->extra)->name.' · '.number_format((float) $line->price, 2)))
                ->filter()->join("\n");
            $data = [
                'booking_reference' => $transaction->ref_no,
                'booking_date' => Carbon::parse($transaction->transaction_date ?: $transaction->created_at)->toDateString(),
                'guest_name' => optional($transaction->contact)->name,
                'stay_period' => $arrival->format('d M Y H:i').' - '.$departure->format('d M Y H:i'),
                'arrival_date' => $arrival->toDateString(),
                'arrival_time' => $arrival->format('H:i'),
                'checkout_date' => $departure->toDateString(),
                'checkout_time' => $departure->format('H:i'),
                'number_of_days' => $nights,
                'stay_duration' => $nights.' '.($nights === 1 ? 'night' : 'nights').' ('.$arrival->diffInHours($departure).' hours)',
                'adults' => (int) $transaction->hms_booking_lines->sum('adults'),
                'children' => (int) $transaction->hms_booking_lines->sum('childrens'),
                'room_summary' => $roomSummary,
                'rate_plan' => optional($transaction->hms_rate_plan)->name,
                'extras' => $revenueExtras,
                'payment_deposit_amount' => $amountPaid,
                'payment_date' => optional($lastPayment)->paid_on
                    ? Carbon::parse($lastPayment->paid_on)->toDateString()
                    : now()->toDateString(),
                'payment_method' => optional($lastPayment)->method
                    ? ucfirst(str_replace('_', ' ', $lastPayment->method))
                    : null,
                'payment_reference' => optional($lastPayment)->payment_ref_no,
            ];
            if ($scenario === 'long_stay') {
                $securityDeposit = SecurityDeposit::forBusiness($businessId)
                    ->where('context_type', 'hms_booking')
                    ->where('context_id', $transaction->id)
                    ->first();
                $data = array_merge($data, [
                    'property_name' => optional($transaction->location)->name,
                    'property_address' => trim(strip_tags(str_ireplace(
                        ['<br>', '<br/>', '<br />'],
                        ', ',
                        (string) optional($transaction->location)->location_address
                    )), " \t\n\r\0\x0B,"),
                    'lease_start' => $arrival->toDateString(),
                    'lease_end' => $departure->toDateString(),
                    'payment_frequency' => 'one_off',
                    'contract_total' => $transaction->final_total,
                    'payment_deposit_amount' => $amountPaid,
                    'security_deposit' => optional($securityDeposit)->required_amount,
                ]);
            }
        } else {
            $documentLines = $transaction->sell_lines
                ->whereNull('parent_sell_line_id')
                ->map(function ($line) {
                    $name = trim(optional($line->product)->name.' '.optional($line->variations)->name);

                    return [
                        'line_type' => 'item',
                        'description' => $name !== '' ? $name : 'Sale item',
                        'quantity' => max(0.0001, (float) $line->quantity),
                        'unit_price' => (float) $line->unit_price_inc_tax,
                        'discount_amount' => 0,
                        'tax_amount' => 0,
                        'metadata' => ['sell_line_id' => $line->id],
                    ];
                })->values()->all();
            $lineTotal = round((float) collect($documentLines)->sum(
                fn ($line) => (float) $line['quantity'] * (float) $line['unit_price']
            ), 4);
            $reconciliation = round((float) $transaction->final_total - $lineTotal, 4);
            if ($documentLines && abs($reconciliation) >= 0.0001) {
                $documentLines[] = [
                    'line_type' => 'charge',
                    'description' => $reconciliation > 0 ? 'Order charges and adjustments' : 'Order discount and adjustments',
                    'quantity' => 1,
                    'unit_price' => $reconciliation,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                ];
            }
        }

        if (! $documentLines) {
            $documentLines = [[
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $transaction->final_total,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]];
        }

        return [
            'scenario_code' => $scenario,
            'preferred_type_code' => $preferred,
            'contact_id' => $transaction->contact_id,
            'location_id' => $transaction->location_id,
            'issue_date' => $transaction->transaction_date
                ? Carbon::parse($transaction->transaction_date)->toDateString()
                : now()->toDateString(),
            'service_start_at' => $isHms ? $transaction->hms_booking_arrival_date_time : null,
            'service_end_at' => $isHms ? $transaction->hms_booking_departure_date_time : null,
            'data' => $data,
            'amount_paid' => $amountPaid,
            'lines' => $documentLines,
        ];
    }

    private function hmsFolio(int $businessId, int $id): array
    {
        $folio = HmsFolio::where('business_id', $businessId)
            ->with(['contact', 'property', 'booking', 'entries'])
            ->findOrFail($id);
        $lines = $folio->entries
            ->whereIn('status', ['posted', 'approved'])
            ->reject(fn ($entry) => $entry->category === 'security_deposit')
            ->map(function ($entry) {
                $amount = $entry->direction === 'debit' ? (float) $entry->amount : -(float) $entry->amount;

                return [
                    'description' => $entry->description,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'metadata' => ['folio_entry_id' => $entry->id, 'category' => $entry->category],
                ];
            })->values()->all();
        $securityContext = $folio->transaction_id
            ? ['hms_booking', (int) $folio->transaction_id]
            : ($folio->hms_event_booking_id ? ['hms_event', (int) $folio->hms_event_booking_id] : null);
        $securityDeposit = $securityContext
            ? SecurityDeposit::forBusiness($businessId)
                ->where('context_type', $securityContext[0])
                ->where('context_id', $securityContext[1])
                ->first()
            : null;

        return [
            'scenario_code' => 'short_stay',
            'preferred_type_code' => 'guest_folio',
            'contact_id' => $folio->contact_id,
            'data' => [
                'booking_reference' => optional($folio->booking)->ref_no,
                'room_summary' => optional($folio->property)->name,
                'security_deposit' => optional($securityDeposit)->required_amount,
            ],
            'lines' => $lines,
        ];
    }

    private function hmsEventBooking(int $businessId, int $id): array
    {
        $event = HmsEventBooking::where('business_id', $businessId)
            ->with(['contact', 'venue', 'property.location', 'folio.entries'])
            ->findOrFail($id);
        $entries = optional($event->folio)->entries ?: collect();
        $billableEntries = $entries->whereIn('status', ['posted', 'approved'])
            ->reject(fn ($entry) => in_array($entry->category, ['payment_deposit', 'booking_payment', 'security_deposit'], true));
        $lines = $billableEntries->map(function ($entry) {
            return [
                'line_type' => $entry->direction === 'debit' ? 'service' : 'discount',
                'description' => $entry->description,
                'quantity' => 1,
                'unit_price' => $entry->direction === 'debit' ? (float) $entry->amount : -(float) $entry->amount,
                'discount_amount' => 0,
                'tax_amount' => (float) ($entry->tax_amount ?? 0),
                'metadata' => ['folio_entry_id' => $entry->id, 'category' => $entry->category],
            ];
        })->values()->all();
        if (! $lines) {
            $lines[] = [
                'line_type' => 'service',
                'description' => 'Event / venue booking: '.$event->event_name,
                'quantity' => 1,
                'unit_price' => (float) $event->agreed_amount,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ];
        }
        $payments = $entries->whereIn('status', ['posted', 'approved'])
            ->where('direction', 'credit')
            ->filter(fn ($entry) => in_array($entry->category, ['payment_deposit', 'booking_payment'], true));
        $lastPayment = $payments->sortByDesc('posted_at')->first();
        $securityDeposit = SecurityDeposit::forBusiness($businessId)
            ->where('context_type', 'hms_event')
            ->where('context_id', $event->id)
            ->first();

        return [
            'scenario_code' => 'event',
            'preferred_type_code' => 'event_booking_invoice',
            'contact_id' => $event->contact_id,
            'location_id' => optional($event->property)->location_id,
            'asset_type' => 'hms_event_venue',
            'asset_id' => $event->hms_event_venue_id,
            'issue_date' => optional($event->created_at)->toDateString() ?: now()->toDateString(),
            'service_start_at' => $event->starts_at,
            'service_end_at' => $event->ends_at,
            'subject' => $event->event_name,
            'data' => [
                'event_reference' => $event->event_number,
                'event_type' => $event->event_type,
                'event_title' => $event->event_name,
                'event_date' => optional($event->starts_at)->toDateString(),
                'start_time' => optional($event->starts_at)->format('H:i'),
                'end_time' => optional($event->ends_at)->format('H:i'),
                'expected_guests' => $event->expected_guests,
                'venue' => trim(optional($event->property)->name.' / '.optional($event->venue)->name, ' /'),
                'security_deposit' => optional($securityDeposit)->required_amount,
                'payment_deposit_amount' => round((float) $payments->sum('amount'), 4),
                'payment_date' => optional($lastPayment)->posted_at ? Carbon::parse($lastPayment->posted_at)->toDateString() : null,
                'payment_method' => optional($lastPayment)->payment_method,
                'payment_reference' => data_get($lastPayment, 'metadata.payment_reference'),
                'additional_notes' => $event->special_requests,
            ],
            'amount_paid' => round((float) $payments->sum('amount'), 4),
            'lines' => $lines,
        ];
    }

    private function businessDocument(int $businessId, int $id): array
    {
        $source = BusinessDocument::forBusiness($businessId)
            ->with(['type', 'lines', 'children.type'])
            ->findOrFail($id);
        abort_unless(in_array($source->status, ['issued', 'sent', 'accepted', 'completed'], true), 422, 'Issue the source document before creating its payment receipt.');
        abort_unless((float) $source->amount_paid > 0, 422, 'Record a payment in the source workflow before generating its receipt.');
        $receiptedAmount = $source->children
            ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
            ->filter(fn ($child) => str_contains(optional($child->type)->code ?: '', 'receipt'))
            ->sum('total_amount');

        $preferred = 'payment_receipt';
        if ($source->scenario_code === 'rental') {
            $preferred = 'rent_receipt';
        } elseif ($source->scenario_code === 'security_deposit') {
            $preferred = 'security_deposit_receipt';
        } elseif (in_array($source->scenario_code, ['short_stay', 'long_stay'], true)) {
            $preferred = 'booking_payment_receipt';
        } elseif ($source->scenario_code === 'event') {
            $preferred = 'event_payment_receipt';
        } elseif (in_array($source->type->code, ['patient_invoice'], true)) {
            $preferred = 'medical_payment_receipt';
        } elseif (in_array($source->type->code, ['tuition_invoice'], true)) {
            $preferred = 'fee_receipt';
        } elseif ($source->type->code === 'membership_invoice') {
            $preferred = 'membership_receipt';
        } elseif ($source->type->code === 'donation_acknowledgement') {
            $preferred = 'donation_receipt';
        } elseif ($source->type->code === 'service_contract') {
            $preferred = 'retainer_receipt';
        }
        $amount = round(max(0, (float) $source->amount_paid - (float) $receiptedAmount), 4);
        abort_if($amount <= 0, 422, 'All recorded payments on this document already have receipts.');
        $data = array_merge((array) $source->data, [
            'invoice_reference' => $source->document_number,
            'payment_date' => now()->toDateString(),
        ]);

        return [
            'scenario_code' => 'payment',
            'preferred_type_code' => $preferred,
            'parent_document_id' => $source->id,
            'location_id' => $source->location_id,
            'contact_id' => $source->contact_id,
            'currency_id' => $source->currency_id,
            'exchange_rate' => $source->exchange_rate,
            'subject' => 'Payment for '.$source->document_number,
            'data' => $data,
            'amount_paid' => max(0, $amount),
            'lines' => [[
                'line_type' => 'payment',
                'description' => 'Payment received for '.$source->document_number,
                'quantity' => 1,
                'unit_price' => max(0, $amount),
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
        ];
    }
}
