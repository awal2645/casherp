<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hms\Entities\HmsRoom;

class RoomStatusService
{
    public function board(int $businessId, ?int $propertyId = null, ?array $locationIds = null)
    {
        $rooms = HmsRoom::query()
            ->with(['type', 'property'])
            ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))
            ->when($propertyId, fn ($query) => $query->where('hms_property_id', $propertyId))
            ->when($locationIds !== null, fn ($query) => $query->whereHas('property', fn ($property) => $property->whereIn('location_id', $locationIds)))
            ->orderBy('hms_property_id')->orderBy('room_number')->get();

        $assignments = DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->leftJoin('contacts as contact', 'contact.id', '=', 'booking.contact_id')
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
            ->whereIn('booking.hms_booking_status', ['tentative', 'reserved', 'checked_in'])
            ->where('booking.hms_booking_arrival_date_time', '<=', now())
            ->where('booking.hms_booking_departure_date_time', '>', now())
            ->when($propertyId, fn ($query) => $query->where('booking.hms_property_id', $propertyId))
            ->select(['line.hms_room_id', 'booking.id as booking_id', 'booking.hms_booking_status', 'booking.hms_booking_departure_date_time', 'contact.name as guest_name'])
            ->orderByRaw("CASE WHEN booking.hms_booking_status = 'checked_in' THEN 0 ELSE 1 END")
            ->get()->unique('hms_room_id')->keyBy('hms_room_id');

        return $rooms->map(function ($room) use ($assignments) {
            $assignment = $assignments->get($room->id);
            $operationalStatus = $room->housekeeping_status === 'out_of_order'
                ? 'out_of_order'
                : ($assignment && $assignment->hms_booking_status === 'checked_in'
                    ? 'occupied'
                    : ($assignment ? 'reserved' : ($room->housekeeping_status ?: 'ready')));
            $room->setAttribute('operational_status', $operationalStatus);
            $room->setAttribute('current_assignment', $assignment);

            return $room;
        });
    }
}
