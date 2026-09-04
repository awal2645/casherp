<?php

namespace App\Services;

use App\Business;
use App\BusinessDocument;
use App\BusinessDocumentEvent;
use App\BusinessDocumentPayment;
use App\BusinessDocumentSetting;
use App\BusinessLocation;
use App\Contact;
use App\Currency;
use App\DocumentType;
use App\PropertyUnit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsEventVenue;

class BusinessDocumentService
{
    private const TRANSITIONS = [
        'draft' => ['issued', 'void'],
        'issued' => ['sent', 'accepted', 'void'],
        'sent' => ['accepted', 'void'],
        'accepted' => ['completed', 'void'],
        'completed' => [],
        'void' => [],
    ];

    public function __construct(
        private IndustryDocumentCatalogService $catalog,
        private BusinessDocumentNumberService $numbers
    ) {
    }

    public function createDraft(Business $business, int $actorId, array $input): BusinessDocument
    {
        $type = $this->catalog->assertAvailable($business, (int) $input['document_type_id']);
        $this->catalog->assertCompatibleScenario($business, $type, (string) $input['scenario_code']);
        $this->assertScopedReferences($business->id, $input);

        return DB::transaction(function () use ($business, $actorId, $input, $type) {
            $amounts = $this->calculateAmounts($input['lines'] ?? []);
            $setting = BusinessDocumentSetting::where('business_id', $business->id)
                ->where('document_type_id', $type->id)
                ->firstOrFail();
            $contact = ! empty($input['contact_id'])
                ? Contact::where('business_id', $business->id)->findOrFail($input['contact_id'])
                : null;
            $location = ! empty($input['location_id'])
                ? BusinessLocation::where('business_id', $business->id)->findOrFail($input['location_id'])
                : $business->locations()->active()->first();
            $data = $this->sanitizeScenarioData($type, (array) ($input['data'] ?? []));
            $currency = Currency::findOrFail($input['currency_id'] ?? $business->currency_id);
            // Quotes, agreements and other non-financial documents cannot
            // record or display payments. Payment evidence belongs to the
            // resulting invoice/receipt workflow.
            $amountPaid = $type->is_financial
                ? round((float) ($input['amount_paid'] ?? 0), 4)
                : 0.0;
            $title = trim((string) ($input['title'] ?? ''))
                ?: ($setting->display_name ?: ($type->industry_display_name ?: $type->default_title));

            $document = BusinessDocument::create([
                'business_id' => $business->id,
                'location_id' => optional($location)->id,
                'contact_id' => optional($contact)->id,
                'document_type_id' => $type->id,
                'parent_document_id' => $input['parent_document_id'] ?? null,
                'source_type' => $input['source_type'] ?? null,
                'source_id' => $input['source_id'] ?? null,
                'asset_type' => $input['asset_type'] ?? null,
                'asset_id' => $input['asset_id'] ?? null,
                // Official sequential numbering is assigned only when a draft
                // passes all issue checks. This avoids consuming fiscal
                // numbers for abandoned drafts while keeping drafts traceable.
                'document_number' => $this->newDraftNumber($business->id),
                'scenario_code' => $input['scenario_code'] ?? $type->scenario_code,
                'title' => $title,
                'subject' => $input['subject'] ?? null,
                'status' => 'draft',
                'issue_date' => $input['issue_date'] ?? now()->toDateString(),
                'valid_until' => $input['valid_until'] ?? null,
                'service_start_at' => $input['service_start_at'] ?? null,
                'service_end_at' => $input['service_end_at'] ?? null,
                'currency_id' => $input['currency_id'] ?? $business->currency_id,
                'exchange_rate' => $input['exchange_rate'] ?? 1,
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount'],
                'tax_amount' => $amounts['tax'],
                'total_amount' => $amounts['total'],
                'amount_paid' => $amountPaid,
                'balance_due' => round(max(0, $amounts['total'] - $amountPaid), 4),
                'notes' => $input['notes'] ?? null,
                'terms' => array_key_exists('terms', $input) ? $input['terms'] : $setting->default_terms,
                'data' => $data,
                'business_snapshot' => $this->businessSnapshot($business, $location),
                'party_snapshot' => $this->partySnapshot($contact),
                'template_snapshot' => $this->templateSnapshot($type, $setting, $currency),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($document, $input['lines'] ?? []);
            $this->replaceSignatories($document, $input['signatories'] ?? [], $type);
            $this->recordEvent($document, 'created', null, 'draft', $actorId, [
                'source_type' => $document->source_type,
                'source_id' => $document->source_id,
            ]);

            return $document->fresh(['type', 'lines', 'signatories', 'events']);
        }, 3);
    }

    public function updateDraft(BusinessDocument $document, Business $business, int $actorId, array $input): BusinessDocument
    {
        if ($document->status !== 'draft') {
            throw ValidationException::withMessages(['document' => 'Only draft documents can be edited.']);
        }
        $this->assertImmutableSource($document, $input);
        $type = $this->catalog->assertAvailable($business, (int) ($input['document_type_id'] ?? $document->document_type_id));
        $this->catalog->assertCompatibleScenario($business, $type, (string) $input['scenario_code']);
        $this->assertScopedReferences($business->id, $input);

        return DB::transaction(function () use ($document, $business, $actorId, $input, $type) {
            $locked = BusinessDocument::forBusiness($business->id)->lockForUpdate()->findOrFail($document->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['document' => 'This document was changed by another user and is no longer editable.']);
            }
            $amounts = $this->calculateAmounts($input['lines'] ?? []);
            $contact = ! empty($input['contact_id'])
                ? Contact::where('business_id', $business->id)->findOrFail($input['contact_id'])
                : null;
            $location = ! empty($input['location_id'])
                ? BusinessLocation::where('business_id', $business->id)->findOrFail($input['location_id'])
                : $business->locations()->active()->first();
            $setting = BusinessDocumentSetting::where('business_id', $business->id)
                ->where('document_type_id', $type->id)
                ->firstOrFail();
            $currency = Currency::findOrFail($input['currency_id'] ?? $business->currency_id);
            $amountPaid = $type->is_financial
                ? round((float) ($input['amount_paid'] ?? 0), 4)
                : 0.0;

            $locked->update([
                'location_id' => optional($location)->id,
                'contact_id' => optional($contact)->id,
                'document_type_id' => $type->id,
                'asset_type' => $input['asset_type'] ?? null,
                'asset_id' => $input['asset_id'] ?? null,
                'scenario_code' => $input['scenario_code'] ?? $type->scenario_code,
                'title' => trim((string) ($input['title'] ?? '')) ?: ($setting->display_name ?: ($type->industry_display_name ?: $type->default_title)),
                'subject' => $input['subject'] ?? null,
                'issue_date' => $input['issue_date'] ?? now()->toDateString(),
                'valid_until' => $input['valid_until'] ?? null,
                'service_start_at' => $input['service_start_at'] ?? null,
                'service_end_at' => $input['service_end_at'] ?? null,
                'currency_id' => $input['currency_id'] ?? $business->currency_id,
                'exchange_rate' => $input['exchange_rate'] ?? 1,
                'subtotal' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount'],
                'tax_amount' => $amounts['tax'],
                'total_amount' => $amounts['total'],
                'amount_paid' => $amountPaid,
                'balance_due' => round(max(0, $amounts['total'] - $amountPaid), 4),
                'notes' => $input['notes'] ?? null,
                'terms' => $input['terms'] ?? null,
                'data' => $this->sanitizeScenarioData($type, (array) ($input['data'] ?? [])),
                'business_snapshot' => $this->businessSnapshot($business, $location),
                'party_snapshot' => $this->partySnapshot($contact),
                'template_snapshot' => $this->templateSnapshot($type, $setting, $currency),
                'updated_by' => $actorId,
            ]);

            $this->replaceLines($locked, $input['lines'] ?? []);
            $this->replaceSignatories($locked, $input['signatories'] ?? [], $type);
            $this->recordEvent($locked, 'updated', 'draft', 'draft', $actorId);

            return $locked->fresh(['type', 'lines', 'signatories', 'events']);
        }, 3);
    }

    public function transition(BusinessDocument $document, string $toStatus, int $actorId): BusinessDocument
    {
        return DB::transaction(function () use ($document, $toStatus, $actorId) {
            $locked = BusinessDocument::where('business_id', $document->business_id)
                ->lockForUpdate()
                ->findOrFail($document->id);
            $from = $locked->status;
            if (! in_array($toStatus, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "A document cannot move from {$from} to {$toStatus}.",
                ]);
            }

            if ($toStatus === 'issued') {
                $this->validateForIssue($locked);
            }

            $timestamps = [
                'issued' => 'issued_at',
                'sent' => 'sent_at',
                'accepted' => 'accepted_at',
                'completed' => 'completed_at',
                'void' => 'voided_at',
            ];
            $changes = ['status' => $toStatus, 'updated_by' => $actorId];
            if ($toStatus === 'issued' && str_starts_with($locked->document_number, 'DRAFT-')) {
                $changes['document_number'] = $this->numbers->next($locked->business_id, $locked->type);
            }
            if (isset($timestamps[$toStatus])) {
                $changes[$timestamps[$toStatus]] = now();
            }
            if ($toStatus === 'void') {
                $changes['public_token_hash'] = null;
                $changes['share_expires_at'] = null;
            }
            $locked->update($changes);
            if ($toStatus === 'issued') {
                $this->createInitialPaymentIfNeeded($locked, $actorId);
            }
            $this->recordEvent($locked, 'status_changed', $from, $toStatus, $actorId);

            return $locked->fresh(['type', 'lines', 'signatories', 'events']);
        }, 3);
    }

    private function newDraftNumber(int $businessId): string
    {
        do {
            $number = 'DRAFT-'.strtoupper(Str::random(12));
        } while (BusinessDocument::forBusiness($businessId)->where('document_number', $number)->exists());

        return $number;
    }

    public function convert(BusinessDocument $document, Business $business, int $actorId): BusinessDocument
    {
        $document->loadMissing(['type', 'lines', 'signatories']);
        $targetCode = $document->type->convert_to_code;
        if (empty($targetCode)) {
            throw ValidationException::withMessages(['document' => 'This document does not have a configured conversion target.']);
        }
        $target = $this->catalog->availableTypes($business)->firstWhere('code', $targetCode);
        if (! $target) {
            throw ValidationException::withMessages(['document' => 'The conversion target is not enabled for this company.']);
        }

        return $this->createDraft($business, $actorId, [
            'document_type_id' => $target->id,
            'parent_document_id' => $document->id,
            'source_type' => $document->source_type,
            'source_id' => $document->source_id,
            'asset_type' => $document->asset_type,
            'asset_id' => $document->asset_id,
            'scenario_code' => $target->scenario_code,
            'location_id' => $document->location_id,
            'contact_id' => $document->contact_id,
            'subject' => $document->subject,
            'issue_date' => now()->toDateString(),
            'valid_until' => null,
            'service_start_at' => optional($document->service_start_at)->toDateTimeString(),
            'service_end_at' => optional($document->service_end_at)->toDateTimeString(),
            'currency_id' => $document->currency_id,
            'exchange_rate' => $document->exchange_rate,
            'amount_paid' => 0,
            'notes' => $document->notes,
            'data' => $document->data ?: [],
            'lines' => $document->lines->map(fn ($line) => Arr::only($line->toArray(), [
                'line_type', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'metadata',
            ]))->all(),
            'signatories' => $document->signatories->map(fn ($signatory) => Arr::only($signatory->toArray(), [
                'role', 'name', 'position', 'identifier', 'phone', 'email', 'address',
            ]))->all(),
        ]);
    }

    public function createShareToken(BusinessDocument $document, int $actorId, int $days = 30): string
    {
        if (! in_array($document->status, ['issued', 'sent', 'accepted', 'completed'], true)) {
            throw ValidationException::withMessages(['share' => 'Issue the document before creating a public link.']);
        }
        $token = Str::random(64);
        $document->update([
            'public_token_hash' => hash('sha256', $token),
            'share_expires_at' => now()->addDays(max(1, min(365, $days))),
            'updated_by' => $actorId,
        ]);
        $this->recordEvent($document, 'share_link_created', $document->status, $document->status, $actorId, [
            'expires_at' => $document->share_expires_at,
        ]);

        return $token;
    }

    public function revokeShare(BusinessDocument $document, int $actorId): void
    {
        $document->update(['public_token_hash' => null, 'share_expires_at' => null, 'updated_by' => $actorId]);
        $this->recordEvent($document, 'share_link_revoked', $document->status, $document->status, $actorId);
    }

    public function recordPayment(BusinessDocument $document, int $actorId, array $input): BusinessDocument
    {
        return DB::transaction(function () use ($document, $actorId, $input) {
            $locked = BusinessDocument::forBusiness($document->business_id)
                ->with('type')
                ->lockForUpdate()
                ->findOrFail($document->id);
            if (! $locked->type->is_financial || str_contains($locked->type->code, 'receipt')) {
                throw ValidationException::withMessages(['payment' => 'Payments can be recorded only against a financial invoice or charge document.']);
            }
            if (! in_array($locked->status, ['issued', 'sent', 'accepted', 'completed'], true)) {
                throw ValidationException::withMessages(['payment' => 'Issue the document before recording a payment.']);
            }

            $amount = round((float) $input['amount'], 4);
            $balance = round(max(0, (float) $locked->total_amount - (float) $locked->amount_paid), 4);
            if ($amount <= 0 || $amount > $balance + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'The payment must be positive and cannot exceed the current document balance.']);
            }
            $reference = trim((string) ($input['reference'] ?? '')) ?: null;
            if ($reference && BusinessDocumentPayment::where('business_document_id', $locked->id)->where('reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'This payment reference is already recorded on the document.']);
            }

            $payment = BusinessDocumentPayment::create([
                'business_id' => $locked->business_id,
                'business_document_id' => $locked->id,
                'payment_date' => $input['payment_date'],
                'amount' => $amount,
                'currency_id' => $locked->currency_id,
                'exchange_rate' => $locked->exchange_rate ?: 1,
                'method' => $input['method'],
                'payment_purpose' => $input['payment_purpose'] ?? 'invoice_payment',
                'reference' => $reference,
                'notes' => $input['notes'] ?? null,
                'status' => 'posted',
                'created_by' => $actorId,
            ]);
            $newPaid = round((float) $locked->amount_paid + $amount, 4);
            $locked->update([
                'amount_paid' => $newPaid,
                'balance_due' => round(max(0, (float) $locked->total_amount - $newPaid), 4),
                'updated_by' => $actorId,
            ]);
            $this->recordEvent($locked, 'payment_recorded', $locked->status, $locked->status, $actorId, [
                'payment_id' => $payment->id,
                'amount' => $amount,
                'method' => $payment->method,
                'payment_purpose' => $payment->payment_purpose,
                'reference' => $payment->reference,
            ]);

            return $locked->fresh(['type', 'payments', 'events']);
        }, 3);
    }

    public function reversePayment(
        BusinessDocument $document,
        BusinessDocumentPayment $payment,
        int $actorId,
        string $reason
    ): BusinessDocument {
        return DB::transaction(function () use ($document, $payment, $actorId, $reason) {
            $locked = BusinessDocument::forBusiness($document->business_id)->lockForUpdate()->findOrFail($document->id);
            $lockedPayment = BusinessDocumentPayment::where('business_id', $locked->business_id)
                ->where('business_document_id', $locked->id)
                ->lockForUpdate()
                ->findOrFail($payment->id);
            if ($lockedPayment->status !== 'posted') {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }

            $newPaid = round(max(0, (float) $locked->amount_paid - (float) $lockedPayment->amount), 4);
            $issuedReceiptTotal = BusinessDocument::forBusiness($locked->business_id)
                ->where('parent_document_id', $locked->id)
                ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
                ->whereHas('type', fn ($query) => $query->where('code', 'like', '%receipt%'))
                ->sum('total_amount');
            if ((float) $issuedReceiptTotal > $newPaid + 0.0001) {
                throw ValidationException::withMessages([
                    'payment' => 'Void the receipt covering this payment before reversing the payment entry.',
                ]);
            }

            $lockedPayment->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversed_by' => $actorId,
                'reversal_reason' => trim($reason),
            ]);
            $locked->update([
                'amount_paid' => $newPaid,
                'balance_due' => round(max(0, (float) $locked->total_amount - $newPaid), 4),
                'updated_by' => $actorId,
            ]);
            $this->recordEvent($locked, 'payment_reversed', $locked->status, $locked->status, $actorId, [
                'payment_id' => $lockedPayment->id,
                'amount' => (float) $lockedPayment->amount,
                'reason' => trim($reason),
            ]);

            return $locked->fresh(['type', 'payments', 'events']);
        }, 3);
    }

    private function calculateAmounts(array $lines): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        foreach ($lines as $line) {
            if (trim((string) ($line['description'] ?? '')) === '') {
                continue;
            }
            $subtotal += (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0);
            $discount += (float) ($line['discount_amount'] ?? 0);
            $tax += (float) ($line['tax_amount'] ?? 0);
        }

        return [
            'subtotal' => round($subtotal, 4),
            'discount' => round($discount, 4),
            'tax' => round($tax, 4),
            'total' => round($subtotal - $discount + $tax, 4),
        ];
    }

    private function replaceLines(BusinessDocument $document, array $lines): void
    {
        $document->lines()->delete();
        foreach (array_values($lines) as $index => $line) {
            $description = trim((string) ($line['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $quantity = round((float) ($line['quantity'] ?? 1), 4);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 4);
            $discount = round((float) ($line['discount_amount'] ?? 0), 4);
            $tax = round((float) ($line['tax_amount'] ?? 0), 4);
            $document->lines()->create([
                'sort_order' => ($index + 1) * 10,
                'line_type' => $line['line_type'] ?? 'item',
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_total' => round(($quantity * $unitPrice) - $discount + $tax, 4),
                'metadata' => Arr::only((array) ($line['metadata'] ?? []), ['source_id', 'source_type', 'category', 'unit']),
            ]);
        }
    }

    private function replaceSignatories(BusinessDocument $document, array $signatories, DocumentType $type): void
    {
        $document->signatories()->delete();
        if (empty($signatories)) {
            $signatories = array_map(fn ($role) => ['role' => $role], $this->catalog->defaultSignatoryRoles($type));
        }
        foreach (array_values($signatories) as $index => $signatory) {
            $role = trim((string) ($signatory['role'] ?? ''));
            if ($role === '') {
                continue;
            }
            $document->signatories()->create([
                'sort_order' => ($index + 1) * 10,
                'role' => $role,
                'name' => $signatory['name'] ?? null,
                'position' => $signatory['position'] ?? null,
                'identifier' => $signatory['identifier'] ?? null,
                'phone' => $signatory['phone'] ?? null,
                'email' => $signatory['email'] ?? null,
                'address' => $signatory['address'] ?? null,
                'status' => 'pending',
            ]);
        }
    }

    private function sanitizeScenarioData(DocumentType $type, array $input): array
    {
        $fields = $this->catalog->schemaFields($type);
        $allowed = collect($fields)->pluck('key')->all();
        $clean = [];
        foreach (Arr::only($input, $allowed) as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', array_slice($value, 0, 50)));
            }
            $clean[$key] = is_string($value) ? trim(Str::limit($value, 10000, '')) : $value;
        }

        return $clean;
    }

    private function validateForIssue(BusinessDocument $document): void
    {
        $document->loadMissing(['type', 'lines', 'signatories']);
        $business = Business::with('industry')->findOrFail($document->business_id);
        $availableType = $this->catalog->assertAvailable($business, $document->document_type_id);
        $this->catalog->assertCompatibleScenario($business, $availableType, $document->scenario_code);
        $isReceipt = str_contains($document->type->code, 'receipt');
        if ($document->source_type && $document->source_id
            && (! $isReceipt || $document->source_type !== 'business_document')) {
            $duplicateExists = BusinessDocument::forBusiness($document->business_id)
                ->where('id', '!=', $document->id)
                ->where('source_type', $document->source_type)
                ->where('source_id', $document->source_id)
                ->where('document_type_id', $document->document_type_id)
                ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
                ->exists();
            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'document' => 'An active issued document of this type already exists for the source record. Open that document or void it before issuing a replacement.',
                ]);
            }
        }
        if ($document->type->supports_line_items && $document->lines->isEmpty()) {
            throw ValidationException::withMessages(['lines' => 'Add at least one line before issuing this document.']);
        }
        $missing = [];
        foreach ($this->catalog->schemaFields($document->type) as $field) {
            if (! empty($field['required']) && blank(data_get($document->data, $field['key']))) {
                $missing[] = $field['label'];
            }
        }
        if ($missing) {
            throw ValidationException::withMessages(['data' => 'Complete these required fields: '.implode(', ', $missing).'.']);
        }
        if ($document->type->requires_acceptance) {
            $setting = BusinessDocumentSetting::where('business_id', $document->business_id)
                ->where('document_type_id', $document->document_type_id)
                ->first();
            if (blank($document->terms)) {
                throw ValidationException::withMessages(['terms' => 'Agreement terms are required before issue.']);
            }
            if (empty($setting) || empty($setting->terms_reviewed_at)) {
                throw ValidationException::withMessages([
                    'terms' => 'A company administrator must review and approve the legal terms in Document Settings before this agreement can be issued.',
                ]);
            }
            if (empty($setting->terms_reviewed_hash)
                || ! hash_equals($setting->terms_reviewed_hash, $this->termsHash($document->terms))) {
                throw ValidationException::withMessages([
                    'terms' => 'The agreement terms differ from the company-approved version. Restore the approved terms or ask an administrator to review the updated wording.',
                ]);
            }
            if (empty($document->contact_id) || empty($document->party_snapshot)) {
                throw ValidationException::withMessages(['contact_id' => 'Select the customer, client, tenant, or counterparty before issuing an agreement.']);
            }
            if ($document->signatories->count() < 2 || $document->signatories->contains(fn ($signatory) => blank($signatory->name))) {
                throw ValidationException::withMessages(['signatories' => 'Enter the names of at least two authorized signatories before issuing an agreement.']);
            }
        }
        if ($isReceipt
            && ((float) $document->total_amount <= 0 || (float) $document->amount_paid <= 0)) {
            throw ValidationException::withMessages(['lines' => 'A receipt must contain a positive received amount and payment total.']);
        }
        if ($isReceipt && abs((float) $document->total_amount - (float) $document->amount_paid) > 0.0001) {
            throw ValidationException::withMessages(['amount_paid' => 'For a receipt, the amount received must equal the receipt total.']);
        }
        if (! $isReceipt && $document->type->is_financial
            && (float) $document->amount_paid > (float) $document->total_amount + 0.0001) {
            throw ValidationException::withMessages(['amount_paid' => 'The amount paid cannot exceed the financial document total.']);
        }
        if ($isReceipt && $document->source_type === 'business_document' && $document->parent_document_id) {
            $source = BusinessDocument::forBusiness($document->business_id)
                ->lockForUpdate()
                ->findOrFail($document->parent_document_id);
            $alreadyReceipted = BusinessDocument::forBusiness($document->business_id)
                ->where('parent_document_id', $source->id)
                ->where('id', '!=', $document->id)
                ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
                ->whereHas('type', fn ($query) => $query->where('code', 'like', '%receipt%'))
                ->sum('total_amount');
            if ((float) $alreadyReceipted + (float) $document->total_amount > (float) $source->amount_paid + 0.0001) {
                throw ValidationException::withMessages([
                    'lines' => 'This receipt exceeds the source document payment that has not yet been receipted.',
                ]);
            }
        }
    }

    private function createInitialPaymentIfNeeded(BusinessDocument $document, int $actorId): void
    {
        if (! Schema::hasTable('business_document_payments')
            || ! $document->type->is_financial
            || str_contains($document->type->code, 'receipt')
            || (float) $document->amount_paid <= 0
            || BusinessDocumentPayment::where('business_document_id', $document->id)->exists()) {
            return;
        }

        $isSecurityDamageApplication = $document->type->code === 'damage_charge_invoice';

        $payment = BusinessDocumentPayment::create([
            'business_id' => $document->business_id,
            'business_document_id' => $document->id,
            'payment_date' => data_get($document->data, 'payment_date', optional($document->issue_date)->toDateString()),
            'amount' => $document->amount_paid,
            'currency_id' => $document->currency_id,
            'exchange_rate' => $document->exchange_rate ?: 1,
            'method' => $isSecurityDamageApplication
                ? 'security_deposit_applied'
                : (data_get($document->data, 'payment_method', 'opening_payment') ?: 'opening_payment'),
            'payment_purpose' => $isSecurityDamageApplication
                ? 'invoice_payment'
                : (in_array($document->scenario_code, ['event', 'rental', 'short_stay', 'long_stay'], true)
                    ? 'payment_deposit'
                    : 'invoice_payment'),
            'reference' => $isSecurityDamageApplication
                ? 'SEC-DEP-APPLIED-'.$document->id
                : (data_get($document->data, 'payment_reference') ?: 'OPEN-'.$document->id),
            'notes' => $isSecurityDamageApplication
                ? 'Refundable security funds retained and applied only after a documented damage charge was issued.'
                : 'Payment recorded when the document was issued.',
            'status' => 'posted',
            'created_by' => $actorId,
        ]);
        $this->recordEvent($document, 'payment_recorded', $document->status, $document->status, $actorId, [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'reference' => $payment->reference,
            'source' => 'initial_document_payment',
        ]);
    }

    private function assertScopedReferences(int $businessId, array $input): void
    {
        if (! empty($input['location_id'])) {
            abort_unless(BusinessLocation::where('business_id', $businessId)->whereKey($input['location_id'])->exists(), 422, 'Invalid company branch.');
            $permitted = auth()->user()->permitted_locations();
            abort_unless($permitted === 'all' || in_array((int) $input['location_id'], array_map('intval', $permitted), true), 403, 'You cannot create documents for this branch.');
        }
        if (! empty($input['contact_id'])) {
            abort_unless(Contact::where('business_id', $businessId)->whereKey($input['contact_id'])->exists(), 422, 'Invalid customer or client.');
        }
        if (! empty($input['parent_document_id'])) {
            abort_unless(BusinessDocument::forBusiness($businessId)->whereKey($input['parent_document_id'])->exists(), 422, 'Invalid parent document.');
        }
        if (($input['scenario_code'] ?? null) === 'event') {
            abort_unless(! empty($input['asset_type']) && ! empty($input['asset_id']), 422, 'Select the exact property unit, hotel event venue, or responsible operating location.');
        }
        if (! empty($input['asset_type']) && ! empty($input['asset_id'])) {
            $validAsset = match ($input['asset_type']) {
                'property_unit' => PropertyUnit::whereKey($input['asset_id'])
                    ->whereHas('property', fn ($query) => $query->where('business_id', $businessId))->exists(),
                'hms_event_venue' => HmsEventVenue::where('business_id', $businessId)
                    ->whereKey($input['asset_id'])->where('is_active', true)->exists(),
                'business_location' => BusinessLocation::where('business_id', $businessId)
                    ->whereKey($input['asset_id'])->exists(),
                default => false,
            };
            abort_unless($validAsset, 422, 'The selected venue asset is not available to the active company.');
        }
        if (! empty($input['source_type']) && ! empty($input['source_id'])) {
            $sourceTables = [
                'transaction' => 'transactions',
                'property_lease' => 'property_leases',
                'property_rent_due' => 'property_rent_dues',
                'property_rent_payment' => 'property_rent_payments',
                'hms_folio' => 'hms_folios',
                'hms_event_booking' => 'hms_event_bookings',
                'business_document' => 'business_documents',
            ];
            $table = $sourceTables[$input['source_type']] ?? null;
            abort_unless($table && DB::table($table)
                ->where('business_id', $businessId)
                ->where('id', $input['source_id'])
                ->exists(), 422, 'Invalid document source for the active company.');
        }
    }

    private function assertImmutableSource(BusinessDocument $document, array $input): void
    {
        $submittedSourceType = $input['source_type'] ?? $document->source_type;
        $submittedSourceId = array_key_exists('source_id', $input) && $input['source_id'] !== null
            ? (int) $input['source_id']
            : $document->source_id;
        $submittedParentId = array_key_exists('parent_document_id', $input) && $input['parent_document_id'] !== null
            ? (int) $input['parent_document_id']
            : $document->parent_document_id;

        if ($submittedSourceType !== $document->source_type
            || (int) ($submittedSourceId ?: 0) !== (int) ($document->source_id ?: 0)
            || (int) ($submittedParentId ?: 0) !== (int) ($document->parent_document_id ?: 0)) {
            throw ValidationException::withMessages([
                'source_id' => 'A document source and its parent link cannot be changed after the draft is created. Create a new document from the correct transaction instead.',
            ]);
        }
    }

    private function businessSnapshot(Business $business, ?BusinessLocation $location): array
    {
        return [
            'name' => $business->name,
            'logo' => $business->logo,
            'tax_label_1' => $business->tax_label_1,
            'tax_number_1' => $business->tax_number_1,
            'tax_label_2' => $business->tax_label_2,
            'tax_number_2' => $business->tax_number_2,
            'location_name' => optional($location)->name,
            'address' => $this->plainAddress(optional($location)->location_address),
            'mobile' => optional($location)->mobile,
            'email' => optional($location)->email,
            'website' => optional($location)->website,
            'industry' => optional($business->industry)->name,
        ];
    }

    private function partySnapshot(?Contact $contact): ?array
    {
        if (! $contact) {
            return null;
        }

        return [
            'name' => $contact->name,
            'business_name' => $contact->supplier_business_name,
            'contact_id' => $contact->contact_id,
            'tax_number' => $contact->tax_number,
            'address' => implode(', ', array_filter([
                $contact->landmark, $contact->city, $contact->state, $contact->country, $contact->zip_code,
            ])),
            'mobile' => $contact->mobile,
            'alternate_number' => $contact->alternate_number,
            'email' => $contact->email,
        ];
    }

    private function plainAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        return trim(preg_replace('/\s*,\s*,+/', ', ', strip_tags(str_ireplace(
            ['<br>', '<br/>', '<br />'],
            ', ',
            $address
        ))), " \t\n\r\0\x0B,");
    }

    private function templateSnapshot(DocumentType $type, BusinessDocumentSetting $setting, Currency $currency): array
    {
        return [
            'version' => 1,
            'type_code' => $type->code,
            'type_name' => $type->name,
            'display_name' => $setting->display_name ?: ($type->industry_display_name ?: $type->default_title),
            'category' => $type->category,
            'output_format' => $type->output_format,
            'schema_key' => $type->schema_key,
            'fields' => $this->catalog->schemaFields($type),
            'requires_acceptance' => $type->requires_acceptance,
            'approved_terms_hash' => $setting->terms_reviewed_hash,
            'currency_code' => $currency->code,
            'currency_symbol' => $currency->symbol,
        ];
    }

    private function termsHash(string $terms): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", trim($terms)));
    }

    private function recordEvent(
        BusinessDocument $document,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorId,
        array $metadata = []
    ): void {
        BusinessDocumentEvent::create([
            'business_id' => $document->business_id,
            'business_document_id' => $document->id,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }
}
