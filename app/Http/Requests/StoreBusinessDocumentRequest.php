<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Asset linkage is meaningful only for an event/venue transaction.
        // Clearing stale browser fields prevents non-event documents from
        // becoming silently attached to a property unit or hotel venue.
        if ($this->input('scenario_code') !== 'event') {
            $this->merge(['asset_type' => null, 'asset_id' => null]);
        }
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'scenario_code' => ['required', 'string', Rule::in(array_keys((array) config('smart_documents.scenarios', [])))],
            'location_id' => ['nullable', 'required_if:scenario_code,event', 'integer'],
            'contact_id' => ['nullable', 'required_if:scenario_code,event', 'integer'],
            'asset_type' => ['nullable', 'required_if:scenario_code,event', Rule::in(['property_unit', 'hms_event_venue', 'business_location'])],
            'asset_id' => ['nullable', 'required_if:scenario_code,event', 'integer', 'min:1'],
            'parent_document_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', 'required_with:source_id', 'string', Rule::in(['transaction', 'property_lease', 'property_rent_due', 'property_rent_payment', 'hms_folio', 'hms_event_booking', 'business_document'])],
            'source_id' => ['nullable', 'required_with:source_type', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'service_start_at' => ['nullable', 'required_if:scenario_code,event', 'date'],
            'service_end_at' => ['nullable', 'required_if:scenario_code,event', 'date', 'after_or_equal:service_start_at'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'amount_paid' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'notes' => ['nullable', 'string', 'max:20000'],
            'terms' => ['nullable', 'string', 'max:100000'],
            'data' => ['nullable', 'array', 'max:100'],
            'data.*' => ['nullable'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.line_type' => ['nullable', 'string', Rule::in(['item', 'service', 'charge', 'payment', 'deposit', 'tax', 'discount', 'note'])],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'between:-9999999999999999,9999999999999999'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'lines.*.metadata' => ['nullable', 'array'],
            'signatories' => ['nullable', 'array', 'max:10'],
            'signatories.*.role' => ['nullable', 'string', 'max:255'],
            'signatories.*.name' => ['nullable', 'string', 'max:255'],
            'signatories.*.position' => ['nullable', 'string', 'max:255'],
            'signatories.*.identifier' => ['nullable', 'string', 'max:255'],
            'signatories.*.phone' => ['nullable', 'string', 'max:100'],
            'signatories.*.email' => ['nullable', 'email', 'max:255'],
            'signatories.*.address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.min' => 'Add at least one document line.',
            'service_end_at.after_or_equal' => 'The service end must be after the service start.',
            'service_start_at.required_if' => 'Enter when this event or venue hire begins.',
            'service_end_at.required_if' => 'Enter when this event or venue hire ends.',
            'asset_id.required_if' => 'Select the exact property unit, hotel event venue, or responsible operating location for this event transaction.',
            'location_id.required_if' => 'Select the operating location responsible for this event transaction.',
            'contact_id.required_if' => 'Select the customer or organization hiring the venue.',
        ];
    }
}
