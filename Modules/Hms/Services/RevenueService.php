<?php

namespace Modules\Hms\Services;

use Carbon\Carbon;
use Modules\Hms\Entities\HmsFolioEntry;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;

class RevenueService
{
    public function dashboard(int $businessId, int $propertyId, Carbon $start, Carbon $end): array
    {
        HmsProperty::where('business_id', $businessId)->findOrFail($propertyId);
        $days = max(1, $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $rooms = HmsRoom::where('hms_property_id', $propertyId)
            ->where('housekeeping_status', '!=', 'out_of_order')
            ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))->count();
        $availableRoomNights = $rooms * $days;

        $bookings = HmsTransactionClass::where('business_id', $businessId)
            ->where('type', 'hms_booking')->where('hms_property_id', $propertyId)
            ->whereIn('hms_booking_status', ['reserved', 'checked_in', 'checked_out'])
            ->where('hms_booking_arrival_date_time', '<=', $end->copy()->endOfDay())
            ->where('hms_booking_departure_date_time', '>', $start->copy()->startOfDay())
            ->with('hms_booking_lines')->get();

        $soldRoomNights = $bookings->sum(function ($booking) use ($start, $end) {
            $arrival = Carbon::parse($booking->hms_booking_arrival_date_time)->max($start->copy()->startOfDay());
            $departure = Carbon::parse($booking->hms_booking_departure_date_time)->min($end->copy()->addDay()->startOfDay());
            return max(0, $arrival->startOfDay()->diffInDays($departure->startOfDay())) * $booking->hms_booking_lines->count();
        });
        $roomRevenue = (float) HmsFolioEntry::where('business_id', $businessId)
            ->whereHas('folio', fn ($query) => $query
                ->where('business_id', $businessId)
                ->where('hms_property_id', $propertyId))
            ->whereDate('business_date', '>=', $start->toDateString())
            ->whereDate('business_date', '<=', $end->toDateString())
            ->whereIn('status', ['posted', 'approved'])
            ->where('category', 'room_charge')
            ->get()->sum(fn ($entry) => $entry->direction === 'debit' ? (float) $entry->base_amount : -(float) $entry->base_amount);

        $allInRange = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')
            ->where('hms_property_id', $propertyId)->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])->get();
        $cancelled = $allInRange->whereIn('hms_booking_status', ['cancelled', 'no_show'])->count();

        return [
            'rooms' => $rooms,
            'available_room_nights' => $availableRoomNights,
            'sold_room_nights' => $soldRoomNights,
            'room_revenue' => round($roomRevenue, 4),
            'occupancy_percent' => $availableRoomNights ? round($soldRoomNights * 100 / $availableRoomNights, 2) : 0,
            'adr' => $soldRoomNights ? round($roomRevenue / $soldRoomNights, 4) : 0,
            'revpar' => $availableRoomNights ? round($roomRevenue / $availableRoomNights, 4) : 0,
            'bookings_created' => $allInRange->count(),
            'cancellation_percent' => $allInRange->count() ? round($cancelled * 100 / $allInRange->count(), 2) : 0,
            'by_source' => $allInRange->groupBy(fn ($booking) => $booking->hms_booking_source ?: 'unknown')
                ->map(fn ($items) => ['bookings' => $items->count(), 'revenue' => round((float) $items->sum('final_total'), 4)])
                ->sortByDesc('bookings'),
        ];
    }
}
