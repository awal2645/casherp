<?php

namespace App\Services;

use App\Business;
use App\BusinessDocument;
use App\BusinessLocation;
use App\DocumentType;
use App\PropertyLease;
use App\PropertyUnit;
use App\SecurityDepositEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsEventVenue;

/**
 * Produces a reviewable, linked financial document for assessed damage.
 * The refundable deposit itself remains outside income and accounting; only
 * the documented damage charge becomes a receivable/recovery when issued.
 */
class DamageChargeDocumentService
{
    public function __construct(
        private BusinessDocumentService $documents,
        private IndustryDocumentCatalogService $catalog
    ) {
    }

    public function forPropertyLease(PropertyLease $lease, SecurityDepositEntry $entry, int $actorId): BusinessDocument
    {
        $lease->loadMissing(['unit.property', 'tenant']);

        return $this->create(
            Business::findOrFail($lease->business_id),
            $entry,
            [
                'location_id' => optional(optional($lease->unit)->property)->business_location_id,
                'contact_id' => $lease->contact_id,
                'source_type' => null,
                'source_id' => null,
                'asset_type' => 'property_unit',
                'asset_id' => $lease->property_unit_id,
                'asset_reference' => trim(optional(optional($lease->unit)->property)->name.' / '.optional($lease->unit)->unit_code, ' /'),
                'subject' => 'Damage or loss assessed against lease #'.$lease->id,
            ],
            $actorId
        );
    }

    public function forEventDocument(BusinessDocument $source, SecurityDepositEntry $entry, int $actorId): BusinessDocument
    {
        return $this->create(
            Business::findOrFail($source->business_id),
            $entry,
            [
                'location_id' => $source->location_id,
                'contact_id' => $source->contact_id,
                'source_type' => null,
                'source_id' => null,
                'parent_document_id' => $source->id,
                'asset_type' => $source->asset_type,
                'asset_id' => $source->asset_id,
                'asset_reference' => $this->eventAssetReference($source),
                'subject' => 'Damage or loss assessed against '.$source->document_number,
            ],
            $actorId
        );
    }

    private function create(Business $business, SecurityDepositEntry $entry, array $context, int $actorId): BusinessDocument
    {
        if (! Schema::hasTable('document_types') || ! Schema::hasTable('business_document_settings')) {
            throw ValidationException::withMessages([
                'damage_document' => 'The Damage Charge Invoice feature is not installed. Run the current database migrations before recording damage.',
            ]);
        }
        if ($entry->business_document_id) {
            $existing = BusinessDocument::forBusiness((int) $business->id)->find($entry->business_document_id);
            if (! $existing) {
                throw ValidationException::withMessages([
                    'damage_document' => 'The linked Damage Charge Invoice could not be found. Restore it before changing this assessment.',
                ]);
            }

            return $existing;
        }

        $this->catalog->provisionForBusiness($business);
        $type = DocumentType::where('code', 'damage_charge_invoice')->where('is_active', true)->first();
        if (! $type || ! $this->catalog->availableTypes($business)->contains('id', $type->id)) {
            throw ValidationException::withMessages([
                'damage_document' => 'Enable Damage Charge Invoice in Smart Document settings before recording property or event damage.',
            ]);
        }

        $deposit = $entry->deposit->load('entries');
        $previousDamage = max(0, $deposit->damage_amount - (float) $entry->amount);
        $availableBeforeAssessment = max(0, $deposit->received_amount - $previousDamage - $deposit->refunded_amount);
        $retainedAmount = min((float) $entry->amount, $availableBeforeAssessment);

        $document = $this->documents->createDraft($business, $actorId, [
            'document_type_id' => $type->id,
            'scenario_code' => 'security_deposit',
            'location_id' => $context['location_id'] ?? null,
            'contact_id' => $context['contact_id'] ?? null,
            'parent_document_id' => $context['parent_document_id'] ?? null,
            'source_type' => $context['source_type'],
            'source_id' => $context['source_id'],
            'asset_type' => $context['asset_type'] ?? null,
            'asset_id' => $context['asset_id'] ?? null,
            'subject' => $context['subject'],
            'issue_date' => optional($entry->occurred_on)->toDateString() ?: now()->toDateString(),
            'currency_id' => $entry->deposit->currency_id ?: $business->currency_id,
            'exchange_rate' => 1,
            'amount_paid' => $retainedAmount,
            'data' => [
                'inspection_date' => optional($entry->occurred_on)->toDateString(),
                'asset_reference' => $context['asset_reference'],
                'damage_description' => $entry->description,
                'evidence_reference' => $entry->evidence_path ?: $entry->reference,
                'assessment_basis' => $entry->description,
                'deposit_deduction' => $retainedAmount,
                'excess_charge' => max(0, (float) $entry->amount - $retainedAmount),
            ],
            'lines' => [[
                'line_type' => 'charge',
                'description' => 'Documented damage/loss: '.$entry->description,
                'quantity' => 1,
                'unit_price' => $entry->amount,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
            'signatories' => [],
            'notes' => 'Generated from refundable security-deposit assessment #'.$entry->id.'. Review tax and accounting treatment before issue.',
        ]);
        $entry->update(['business_document_id' => $document->id]);

        return $document;
    }

    private function eventAssetReference(BusinessDocument $source): string
    {
        if ($source->asset_type === 'property_unit') {
            $unit = PropertyUnit::whereKey($source->asset_id)->with('property')->first();

            return trim(optional(optional($unit)->property)->name.' / '.optional($unit)->unit_code, ' /');
        }
        if ($source->asset_type === 'hms_event_venue') {
            $venue = HmsEventVenue::where('business_id', $source->business_id)->with('property')->find($source->asset_id);

            return trim(optional(optional($venue)->property)->name.' / '.optional($venue)->name, ' /');
        }
        $location = BusinessLocation::where('business_id', $source->business_id)->find($source->asset_id);

        return optional($location)->name ?: $source->document_number.' operating location';
    }
}
