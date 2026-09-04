<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsEventVenue;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;

class EventVenueController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_event_venues');
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $venues = HmsEventVenue::where('business_id', $businessId)->with('property')->orderBy('name')->get();

        return view('hms::event_venues.index', compact('properties', 'venues'));
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_event_venues');
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', Rule::unique('hms_event_venues')->where(fn ($query) => $query->where('business_id', $businessId)->where('hms_property_id', $request->integer('hms_property_id')))],
            'venue_type' => ['required', Rule::in(['event_hall', 'conference_room', 'garden', 'rooftop', 'restaurant', 'meeting_room', 'other'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:3000'],
        ]);
        HmsEventVenue::create($data + ['business_id' => $businessId, 'is_active' => true, 'created_by' => auth()->id()]);

        return back()->with('status', ['success' => 1, 'msg' => 'Event venue added. It is now selectable on event contracts and invoices.']);
    }

    public function toggle($venue)
    {
        $businessId = $this->authorizeHms('hms.manage_event_venues');
        $model = HmsEventVenue::where('business_id', $businessId)->findOrFail($venue);
        $model->update(['is_active' => ! $model->is_active]);

        return back()->with('status', ['success' => 1, 'msg' => 'Event venue availability updated.']);
    }
}
