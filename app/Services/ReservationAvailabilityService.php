<?php

namespace App\Services;

use App\Restaurant\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    public function assertAvailable(
        int $businessId,
        int $locationId,
        ?int $tableId,
        Carbon $start,
        Carbon $end,
        ?int $ignoreBookingId = null
    ): void {
        if (! $start->lt($end)) {
            throw ValidationException::withMessages(['booking_end' => 'The reservation end must be after its start.']);
        }
        // A table-less enquiry or pickup reservation does not reserve the
        // entire location. Capacity controls apply only after a real table is
        // selected; otherwise unrelated reservations could block each other.
        if (! $tableId) {
            return;
        }

        DB::transaction(function () use ($businessId, $locationId, $tableId, $start, $end, $ignoreBookingId) {
            $query = Booking::where('business_id', $businessId)
                ->where('location_id', $locationId)
                ->whereNotIn('booking_status', ['cancelled', 'completed'])
                // Correct interval overlap: existing start < requested end AND existing end > requested start.
                ->where('booking_start', '<', $end->toDateTimeString())
                ->where('booking_end', '>', $start->toDateTimeString());

            $query->where('table_id', $tableId);
            if ($ignoreBookingId) {
                $query->where('id', '!=', $ignoreBookingId);
            }

            $conflict = $query->lockForUpdate()->first();
            if ($conflict) {
                throw ValidationException::withMessages([
                    'booking_start' => 'This table or service period already has an active reservation.',
                ]);
            }
        });
    }
}
