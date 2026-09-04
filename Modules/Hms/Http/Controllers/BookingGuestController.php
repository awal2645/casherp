<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsBookingGuest;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\HmsContextService;

class BookingGuestController extends Controller
{
    use AuthorizesHmsRequests;

    public function store(Request $request, int $booking, HmsContextService $context)
    {
        $businessId = $this->authorizeHms('hms.manage_stay_guests');
        $stay = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')->findOrFail($booking);
        $context->property($businessId, (int) $stay->hms_property_id);
        $data = $request->validate([
            'hms_booking_line_id' => ['nullable', 'integer', Rule::exists('hms_booking_lines', 'id')->where('transaction_id', $stay->id)],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'full_name' => ['required', 'string', 'max:255'],
            'guest_type' => ['required', Rule::in(['adult', 'child', 'infant'])],
            'is_primary' => ['nullable', 'boolean'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'identity_document_type' => ['nullable', 'string', 'max:40'],
            'identity_document_last_four' => ['nullable', 'string', 'max:8'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $capacity = (int) $stay->hms_booking_lines()->sum('adults') + (int) $stay->hms_booking_lines()->sum('childrens');
        if ($stay->hms_booking_guests()->count() >= max(1, $capacity)) {
            return back()->withErrors(['full_name' => 'Registered occupants cannot exceed the booked room capacity.'])->withInput();
        }
        if (! empty($data['is_primary'])) {
            $stay->hms_booking_guests()->update(['is_primary' => false]);
        }
        HmsBookingGuest::create($data + [
            'business_id' => $businessId,
            'transaction_id' => $stay->id,
            'is_primary' => ! empty($data['is_primary']),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Stay occupant registered.']);
    }

    public function destroy(Request $request, int $guest, HmsContextService $context)
    {
        $businessId = $this->authorizeHms('hms.manage_stay_guests');
        $model = HmsBookingGuest::where('business_id', $businessId)->findOrFail($guest);
        $stay = HmsTransactionClass::where('business_id', $businessId)->findOrFail($model->transaction_id);
        $context->property($businessId, (int) $stay->hms_property_id);
        $model->delete();

        return back()->with('status', ['success' => 1, 'msg' => 'Stay occupant removed.']);
    }
}
