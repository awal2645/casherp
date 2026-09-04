<?php

namespace App\Services;

use App\Business;
use App\BusinessDocumentSetting;
use App\DocumentType;
use App\Industry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class IndustryDocumentCatalogService
{
    public function provisionForBusiness(Business $business): void
    {
        if (empty($business->industry_id)
            || ! Schema::hasTable('document_types')
            || ! Schema::hasTable('industry_document_types')
            || ! Schema::hasTable('business_document_settings')) {
            return;
        }

        $types = DocumentType::query()
            ->join('industry_document_types as idt', 'idt.document_type_id', '=', 'document_types.id')
            ->where('idt.industry_id', $business->industry_id)
            ->where('idt.enabled_by_default', true)
            ->where('document_types.is_active', true)
            ->select('document_types.*')
            ->get();

        foreach ($types as $type) {
            $definition = (array) config('smart_documents.types.'.$type->code, []);
            BusinessDocumentSetting::firstOrCreate(
                ['business_id' => $business->id, 'document_type_id' => $type->id],
                [
                    'is_enabled' => true,
                    'source' => 'industry_default',
                    'display_name' => null,
                    'prefix' => $definition['prefix'] ?? $type->default_prefix,
                    'next_number' => 1,
                ]
            );
        }
    }

    public function availableTypes(Business $business): Collection
    {
        $this->provisionForBusiness($business);

        return DocumentType::query()
            ->join('industry_document_types as idt', function ($join) use ($business) {
                $join->on('idt.document_type_id', '=', 'document_types.id')
                    ->where('idt.industry_id', '=', $business->industry_id)
                    ->where('idt.enabled_by_default', '=', true);
            })
            ->join('business_document_settings as bds', function ($join) use ($business) {
                $join->on('bds.document_type_id', '=', 'document_types.id')
                    ->where('bds.business_id', '=', $business->id)
                    ->where('bds.is_enabled', '=', true);
            })
            ->where('document_types.is_active', true)
            ->orderBy('idt.sort_order')
            ->select([
                'document_types.*',
                'idt.display_name as industry_display_name',
                'bds.display_name as business_display_name',
                'bds.prefix as business_prefix',
                'bds.default_terms as business_default_terms',
                'bds.terms_reviewed_at',
            ])
            ->get();
    }

    /**
     * All types belonging to the company's industry, including types disabled
     * for new creation. Issued history must remain discoverable after a type
     * is disabled.
     */
    public function profileTypes(Business $business): Collection
    {
        $this->provisionForBusiness($business);

        return DocumentType::query()
            ->join('industry_document_types as idt', function ($join) use ($business) {
                $join->on('idt.document_type_id', '=', 'document_types.id')
                    ->where('idt.industry_id', '=', $business->industry_id);
            })
            ->leftJoin('business_document_settings as bds', function ($join) use ($business) {
                $join->on('bds.document_type_id', '=', 'document_types.id')
                    ->where('bds.business_id', '=', $business->id);
            })
            ->where('document_types.is_active', true)
            ->orderBy('idt.sort_order')
            ->select([
                'document_types.*',
                'idt.display_name as industry_display_name',
                'bds.display_name as business_display_name',
                'bds.is_enabled as business_is_enabled',
            ])
            ->get();
    }

    public function defaultScenario(Business $business): string
    {
        $industryCode = optional($business->industry)->code;

        return (string) config('smart_documents.default_scenario.'.$industryCode, 'sale');
    }

    public function recommendations(Business $business, string $scenario, array $context = []): Collection
    {
        $available = $this->availableTypes($business);
        $availableByCode = $available->keyBy('code');
        $industry = optional($business->industry)->code;
        $purpose = $context['purpose'] ?? null;
        $preferred = $context['preferred_type_code'] ?? null;

        $candidateCodes = [];

        if ($scenario === 'payment') {
            if ($preferred === 'event_payment_receipt') {
                $candidateCodes[] = 'event_payment_receipt';
            }
            if ($industry === 'property_management_rentals') {
                $candidateCodes[] = $purpose === 'security_deposit' || $preferred === 'security_deposit_receipt' ? 'security_deposit_receipt' : 'rent_receipt';
                $candidateCodes[] = $candidateCodes[0] === 'security_deposit_receipt' ? 'rent_receipt' : 'security_deposit_receipt';
            } elseif (in_array($industry, ['hotel_lodge_guesthouse', 'hotel_with_restaurant'], true)) {
                $candidateCodes[] = $preferred === 'event_payment_receipt'
                    ? 'event_payment_receipt'
                    : ($preferred === 'pos_receipt' ? 'pos_receipt' : 'booking_payment_receipt');
            } elseif ($industry === 'professional_services') {
                if ($purpose === 'retainer' || $preferred === 'retainer_receipt') {
                    $candidateCodes[] = 'retainer_receipt';
                }
            } elseif ($industry === 'healthcare_clinic') {
                $candidateCodes[] = 'medical_payment_receipt';
            } elseif ($industry === 'education_training') {
                $candidateCodes[] = 'fee_receipt';
            } elseif ($industry === 'nonprofit_membership') {
                $candidateCodes[] = $purpose === 'membership' || $preferred === 'membership_receipt' ? 'membership_receipt' : 'donation_receipt';
                $candidateCodes[] = $candidateCodes[0] === 'membership_receipt' ? 'donation_receipt' : 'membership_receipt';
            } elseif (in_array($industry, ['general_business', 'restaurant_food_service', 'hotel_with_restaurant', 'retail_wholesale'], true)) {
                $candidateCodes[] = 'pos_receipt';
            }
            $candidateCodes[] = 'payment_receipt';
            if ($industry === 'professional_services' && ! in_array('retainer_receipt', $candidateCodes, true)) {
                $candidateCodes[] = 'retainer_receipt';
            }
        } elseif ($scenario === 'rental') {
            $candidateCodes = array_merge($candidateCodes, ['rental_quotation', 'lease_agreement', 'rental_invoice']);
        } elseif ($scenario === 'security_deposit') {
            $candidateCodes = array_merge($candidateCodes, ['security_deposit_request', 'security_deposit_receipt', 'damage_assessment', 'damage_charge_invoice', 'security_deposit_settlement']);
        } elseif ($scenario === 'short_stay') {
            $candidateCodes = array_merge($candidateCodes, ['room_booking_quotation', 'room_booking_invoice', 'guest_folio', 'booking_payment_receipt']);
        } elseif ($scenario === 'long_stay') {
            $candidateCodes = array_merge($candidateCodes, ['long_stay_agreement', 'room_booking_invoice', 'booking_payment_receipt']);
        } elseif ($scenario === 'event') {
            $candidateCodes = array_merge($candidateCodes, ['event_quotation', 'event_reservation_contract', 'event_booking_invoice']);
        } elseif ($scenario === 'catering') {
            $candidateCodes = array_merge($candidateCodes, ['catering_quotation', 'catering_invoice']);
        } elseif ($scenario === 'professional_service') {
            $candidateCodes = array_merge($candidateCodes, ['service_proposal', 'service_contract', 'service_invoice', 'retainer_receipt']);
        } elseif ($scenario === 'retail_order') {
            $candidateCodes = array_merge($candidateCodes, ['sales_order', 'delivery_note', 'standard_invoice', 'pos_receipt', 'goods_return_note']);
        } elseif ($scenario === 'construction_project') {
            $candidateCodes = array_merge($candidateCodes, ['construction_estimate', 'construction_work_order', 'progress_invoice', 'completion_certificate']);
        } elseif ($scenario === 'healthcare_service') {
            $candidateCodes = array_merge($candidateCodes, ['patient_estimate', 'patient_invoice', 'insurance_claim_summary', 'medical_payment_receipt']);
        } elseif ($scenario === 'education_fee') {
            $candidateCodes = array_merge($candidateCodes, ['enrollment_confirmation', 'tuition_invoice', 'fee_receipt']);
        } elseif ($scenario === 'automotive_service') {
            $candidateCodes = array_merge($candidateCodes, ['vehicle_service_estimate', 'repair_work_order', 'vehicle_service_invoice', 'payment_receipt']);
        } elseif ($scenario === 'logistics_shipment') {
            $candidateCodes = array_merge($candidateCodes, ['freight_quotation', 'consignment_note', 'freight_invoice', 'proof_of_delivery']);
        } elseif ($scenario === 'donation_membership') {
            $candidateCodes = array_merge($candidateCodes, ['donation_acknowledgement', 'donation_receipt', 'membership_invoice', 'membership_receipt']);
        } else {
            $candidateCodes = array_merge($candidateCodes, ['standard_quotation', 'proforma_invoice', 'standard_invoice', 'payment_receipt']);
        }

        $candidateCodes = array_values(array_unique($candidateCodes));
        if ($preferred && in_array($preferred, $candidateCodes, true)) {
            $candidateCodes = array_values(array_unique(array_merge([$preferred], $candidateCodes)));
        }
        $recommended = collect($candidateCodes)
            ->filter(fn ($code) => $availableByCode->has($code))
            ->map(fn ($code) => $availableByCode->get($code));

        return $recommended->values();
    }

    public function availableScenarios(Business $business): array
    {
        return collect((array) config('smart_documents.scenarios', []))
            ->filter(fn ($definition, $scenario) => $this->recommendations($business, $scenario)->isNotEmpty())
            ->all();
    }

    public function assertAvailable(Business $business, int $documentTypeId): DocumentType
    {
        $type = $this->availableTypes($business)->firstWhere('id', $documentTypeId);
        abort_unless($type, 422, 'The selected document type is not enabled for this company.');

        return $type;
    }

    public function assertCompatibleScenario(Business $business, DocumentType $type, string $scenario): void
    {
        $allowed = $this->recommendations($business, $scenario)->contains('id', $type->id);
        abort_unless($allowed, 422, 'The selected document type is not valid for this business scenario.');
    }

    public function schemaFields(DocumentType $type): array
    {
        return (array) config('smart_documents.schema_fields.'.$type->schema_key, []);
    }

    public function defaultSignatoryRoles(DocumentType $type): array
    {
        if ($type->code === 'event_reservation_contract') {
            return ['Client / Organiser', 'Witness for Client', 'Service Provider', 'Witness for Service Provider'];
        }
        if (in_array($type->code, ['lease_agreement', 'long_stay_agreement'], true)) {
            return ['Tenant / Lessee', 'Property Owner / Lessor'];
        }
        if ($type->code === 'service_contract') {
            return ['Client', 'Service Provider'];
        }
        if ($type->code === 'construction_work_order') {
            return ['Client / Employer', 'Contractor'];
        }
        if ($type->code === 'completion_certificate') {
            return ['Client / Employer', 'Contractor', 'Architect / Engineer'];
        }
        if ($type->code === 'repair_work_order') {
            return ['Vehicle Owner / Customer', 'Authorized Workshop'];
        }
        if ($type->code === 'proof_of_delivery') {
            return ['Delivered by', 'Received by'];
        }

        return $type->requires_acceptance ? ['Customer / Client', 'Service Provider'] : [];
    }

    public function industryTypes(Industry $industry): Collection
    {
        return $industry->documentTypes()->orderBy('industry_document_types.sort_order')->get();
    }
}
