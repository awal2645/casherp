<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsBookingLine;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomMove;
use Modules\Hms\Entities\HmsTransactionClass;

class RoomMoveService
{
    public function move(int $businessId, int $bookingLineId, int $destinationRoomId, string $reason, int $actorId, ?string $notes = null): HmsRoomMove
    {
        return DB::transaction(function () use ($businessId, $bookingLineId, $destinationRoomId, $reason, $actorId, $notes) {
            $line = HmsBookingLine::with('transaction')->whereKey($bookingLineId)->lockForUpdate()->firstOrFail();
            $booking = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')->whereKey($line->transaction_id)->lockForUpdate()->firstOrFail();
            if (! in_array($booking->hms_booking_status, ['reserved', 'checked_in'], true)) {
                throw ValidationException::withMessages(['booking' => 'Only reserved or checked-in stays can be moved.']);
            }

            $rooms = HmsRoom::with('type')->whereIn('id', [(int) $line->hms_room_id, $destinationRoomId])->lockForUpdate()->get()->keyBy('id');
            $from = $rooms->get((int) $line->hms_room_id);
            $to = $rooms->get($destinationRoomId);
            if (! $from || ! $to || (int) $from->hms_property_id !== (int) $to->hms_property_id || (int) $booking->hms_property_id !== (int) $to->hms_property_id) {
                throw ValidationException::withMessages(['to_room_id' => 'Select an accessible room in the same hotel property.']);
            }
            if ((int) $from->id === (int) $to->id) {
                throw ValidationException::withMessages(['to_room_id' => 'The destination must be a different room.']);
            }
            if ($to->housekeeping_status !== 'ready') {
                throw ValidationException::withMessages(['to_room_id' => 'The destination room must be inspected and ready.']);
            }
            if ((int) $line->adults > (int) $to->type->no_of_adult
                || (int) $line->childrens > (int) $to->type->no_of_child
                || ((int) $line->adults + (int) $line->childrens) > (int) $to->type->max_occupancy) {
                throw ValidationException::withMessages(['to_room_id' => 'The destination room cannot accommodate the registered guests.']);
            }

            app(BookingIntegrityService::class)->assertRoomAvailableForMove(
                $businessId,
                (int) $to->id,
                \Carbon\Carbon::parse($booking->hms_booking_arrival_date_time),
                \Carbon\Carbon::parse($booking->hms_booking_departure_date_time),
                (int) $booking->id,
                $booking->hms_group_booking_id ? (int) $booking->hms_group_booking_id : null
            );

            $move = HmsRoomMove::create([
                'business_id' => $businessId,
                'transaction_id' => $booking->id,
                'hms_booking_line_id' => $line->id,
                'from_room_id' => $from->id,
                'to_room_id' => $to->id,
                'moved_at' => now(),
                'reason' => $reason,
                'notes' => $notes,
                'moved_by' => $actorId,
            ]);
            $line->update(['hms_room_id' => $to->id, 'hms_room_type_id' => $to->hms_room_type_id]);
            if ($booking->hms_booking_status === 'checked_in') {
                $from->update(['housekeeping_status' => 'dirty']);
            }

            return $move;
        });
    }
}
