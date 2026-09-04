<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\HmsContextService;
use Modules\Hms\Services\RoomStatusService;

class RoomStatusBoardController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request, HmsContextService $context, RoomStatusService $statuses)
    {
        $businessId = $this->authorizeHms('hms.room_status_board');
        $propertiesQuery = $context->scopeProperties(HmsProperty::query(), $businessId)->where('is_active', true);
        $properties = (clone $propertiesQuery)->orderBy('name')->pluck('name', 'id');
        $propertyId = $request->filled('property_id') ? $request->integer('property_id') : null;
        if ($propertyId) {
            $context->property($businessId, $propertyId);
        }
        $rooms = $statuses->board($businessId, $propertyId, $context->permittedLocationIds($businessId));

        $activeBookings = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')
            ->whereIn('hms_booking_status', ['reserved', 'checked_in'])
            ->when($propertyId, fn ($query) => $query->where('hms_property_id', $propertyId))
            ->with(['contact', 'hms_booking_lines.room', 'hms_booking_guests'])
            ->orderBy('hms_booking_arrival_date_time')->get();
        $availableRooms = HmsRoom::with('type')->whereIn('id', $rooms->where('operational_status', 'ready')->pluck('id'))->get();

        return view('hms::room_status.index', compact('properties', 'propertyId', 'rooms', 'activeBookings', 'availableRooms'));
    }
}
