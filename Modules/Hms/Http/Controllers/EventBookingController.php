<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsEventVenue;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\EventBookingService;
use Modules\Hms\Services\HmsContextService;
use App\Contact;

class EventBookingController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request, HmsContextService $context)
    {
        $businessId = $this->authorizeHms('hms.manage_events', 'hms_event_operations');
        $propertyIds = $context->scopeProperties(HmsProperty::query(), $businessId)->pluck('id');
        $properties = HmsProperty::whereIn('id', $propertyIds)->orderBy('name')->pluck('name', 'id');
        $venues = HmsEventVenue::where('business_id', $businessId)->whereIn('hms_property_id', $propertyIds)->where('is_active', true)->with('property')->orderBy('name')->get();
        $contacts = Contact::where('business_id', $businessId)->where('type', 'customer')->orderBy('name')->pluck('name', 'id');
        $events = HmsEventBooking::where('business_id', $businessId)->whereIn('hms_property_id', $propertyIds)
            ->with(['venue', 'property', 'contact', 'folio.entries'])->orderByDesc('starts_at')->paginate(30);

        return view('hms::events.index', compact('properties', 'venues', 'contacts', 'events'));
    }

    public function store(Request $request, HmsContextService $context, EventBookingService $events)
    {
        $businessId = $this->authorizeHms('hms.manage_events', 'hms_event_operations');
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer'],
            'hms_event_venue_id' => ['required', 'integer'],
            'contact_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'event_name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', Rule::in(['wedding', 'meeting', 'conference', 'party', 'exhibition', 'training', 'catering', 'other'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'expected_guests' => ['required', 'integer', 'min:1', 'max:1000000'],
            'guaranteed_guests' => ['nullable', 'integer', 'min:0', 'lte:expected_guests'],
            'agreed_amount' => ['required', 'numeric', 'min:0'],
            'payment_deposit_required' => ['nullable', 'numeric', 'min:0', 'lte:agreed_amount'],
            'special_requests' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $property = $context->property($businessId, (int) $data['hms_property_id']);
        $venue = HmsEventVenue::where('business_id', $businessId)->where('hms_property_id', $property->id)->findOrFail($data['hms_event_venue_id']);
        $events->create($businessId, $data, (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Event booking created with its own operational folio.']);
    }

    public function transition(Request $request, int $event, EventBookingService $events)
    {
        $businessId = $this->authorizeHms('hms.manage_events', 'hms_event_operations');
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'in_progress', 'completed', 'cancelled'])]]);
        $events->transition($businessId, $event, $data['status']);

        return back()->with('status', ['success' => 1, 'msg' => 'Event status updated.']);
    }

    public function addCharge(Request $request, int $event, EventBookingService $events)
    {
        $businessId = $this->authorizeHms('hms.manage_events', 'hms_event_operations');
        app(\Modules\Hms\Services\HmsSaasService::class)->assertCapability($businessId, 'hms_finance_operations');
        $data = $request->validate([
            'category' => ['required', Rule::in(['venue', 'food', 'beverage', 'equipment', 'service', 'payment_deposit', 'discount', 'other'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'required_if:category,payment_deposit', 'string', 'max:40'],
            'idempotency_token' => ['nullable', 'uuid'],
        ]);
        $data['idempotency_token'] = $data['idempotency_token'] ?? (string) Str::uuid();
        $events->addCharge($businessId, $event, $data, (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Event folio updated.']);
    }
}
