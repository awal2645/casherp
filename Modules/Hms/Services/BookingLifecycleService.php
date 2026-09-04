<?php

namespace Modules\Hms\Services;

use App\Services\SecurityDepositService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsBookingEvent;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsGroupRoomBlock;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;

class BookingLifecycleService
{
    private const TRANSITIONS = [
        'tentative' => ['reserved', 'cancelled', 'no_show'],
        'reserved' => ['tentative', 'checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public function __construct(
        protected HousekeepingService $housekeepingService,
        protected SecurityDepositService $securityDepositService
    ) {
    }

    public function initialize(
        HmsTransactionClass $booking,
        string $legacyStatus,
        int $actorId
    ): void {
        $status = $this->fromLegacyStatus($legacyStatus);
        $booking->hms_booking_status = $status;
        $booking->hms_last_status_changed_at = now();

        if ($status === 'cancelled') {
            $booking->hms_cancelled_at = now();
        }

        $booking->save();
        $this->record($booking, 'booking_created', null, $status, $actorId);
        if ($status === 'cancelled') {
            $this->releaseGroupPickup($booking);
        }
    }

    public function synchronizeReservationStatus(
        HmsTransactionClass $booking,
        string $legacyStatus,
        int $actorId,
        ?string $reason = null
    ): void {
        $target = $this->fromLegacyStatus($legacyStatus);
        $current = $this->status($booking);

        if ($target === $current) {
            return;
        }

        if ($target === 'cancelled') {
            $this->applyTransition($booking, 'cancelled', $actorId, $reason);
            $this->releaseGroupPickup($booking);

            return;
        }

        $this->applyTransition($booking, $target, $actorId);
    }

    public function checkIn(
        int $businessId,
        int $bookingId,
        Carbon $occurredAt,
        int $actorId,
        ?string $notes = null
    ): HmsTransactionClass {
        return DB::transaction(function () use (
            $businessId,
            $bookingId,
            $occurredAt,
            $actorId,
            $notes
        ) {
            $booking = $this->lockedBooking($businessId, $bookingId);
            $this->assertStatus($booking, 'reserved');

            $roomIds = $booking->hms_booking_lines()
                ->pluck('hms_room_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $rooms = HmsRoom::whereIn('id', $roomIds)
                ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))
                ->lockForUpdate()
                ->get();

            if ($roomIds->isEmpty() || $rooms->count() !== $roomIds->count()) {
                throw ValidationException::withMessages([
                    'booking' => __('hms::lang.invalid_room_selection'),
                ]);
            }

            $notReady = $rooms->where('housekeeping_status', '!=', 'ready');
            if ($notReady->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'booking' => __('hms::lang.rooms_not_ready', [
                        'rooms' => $notReady->pluck('room_number')->implode(', '),
                    ]),
                ]);
            }

            $booking->check_in = $occurredAt;
            $this->applyTransition($booking, 'checked_in', $actorId, $notes);

            HmsRoom::whereIn('id', $roomIds)->update(['housekeeping_status' => 'occupied']);

            return $booking->fresh();
        });
    }

    public function checkOut(
        int $businessId,
        int $bookingId,
        Carbon $occurredAt,
        int $actorId,
        ?string $notes = null
    ): HmsTransactionClass {
        return DB::transaction(function () use (
            $businessId,
            $bookingId,
            $occurredAt,
            $actorId,
            $notes
        ) {
            $booking = $this->lockedBooking($businessId, $bookingId);
            $this->assertStatus($booking, 'checked_in');

            if ($booking->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment_status' => __('hms::lang.checkout_requires_payment'),
                ]);
            }

            $unsettledFolio = Schema::hasTable('hms_folios')
                && HmsFolio::where('business_id', $businessId)
                    ->where('transaction_id', $bookingId)
                    ->with('entries')
                    ->get()
                    ->contains(fn ($folio) => $folio->balance > 0.0001);
            if ($unsettledFolio) {
                throw ValidationException::withMessages([
                    'payment_status' => __('hms::lang.checkout_requires_settled_folio'),
                ]);
            }

            // Refundable security funds live outside the folio/accounting
            // ledger, but they must be settled, refunded, deducted or formally
            // waived before the physical checkout can close.
            $this->securityDepositService->assertContextCanClose($businessId, 'hms_booking', $bookingId);

            if ($booking->check_in && $occurredAt->lessThan(Carbon::parse($booking->check_in))) {
                throw ValidationException::withMessages([
                    'in_out_date_time' => __('hms::lang.checkout_after_checkin'),
                ]);
            }

            $booking->check_out = $occurredAt;
            $this->applyTransition($booking, 'checked_out', $actorId, $notes);
            $this->housekeepingService->createCheckoutTasks($booking, $actorId);

            return $booking->fresh();
        });
    }

    public function cancel(
        int $businessId,
        int $bookingId,
        string $reason,
        int $actorId
    ): HmsTransactionClass {
        return DB::transaction(function () use ($businessId, $bookingId, $reason, $actorId) {
            $booking = $this->lockedBooking($businessId, $bookingId);
            $booking->hms_cancellation_reason = $reason;
            $booking->hms_cancelled_at = now();
            $this->applyTransition($booking, 'cancelled', $actorId, $reason);
            $this->releaseGroupPickup($booking);

            return $booking->fresh();
        });
    }

    public function markNoShow(
        int $businessId,
        int $bookingId,
        string $reason,
        int $actorId
    ): HmsTransactionClass {
        return DB::transaction(function () use ($businessId, $bookingId, $reason, $actorId) {
            $booking = $this->lockedBooking($businessId, $bookingId);

            if (Carbon::parse($booking->hms_booking_arrival_date_time)->isFuture()) {
                throw ValidationException::withMessages([
                    'reason' => __('hms::lang.no_show_before_arrival'),
                ]);
            }

            $booking->hms_no_show_at = now();
            $booking->hms_cancellation_reason = $reason;
            $this->applyTransition($booking, 'no_show', $actorId, $reason);
            $this->releaseGroupPickup($booking);

            return $booking->fresh();
        });
    }

    public function record(
        HmsTransactionClass $booking,
        string $eventType,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $actorId,
        ?string $notes = null,
        array $metadata = []
    ): HmsBookingEvent {
        return HmsBookingEvent::create([
            'business_id' => (int) $booking->business_id,
            'transaction_id' => (int) $booking->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $actorId,
            'occurred_at' => now(),
            'notes' => $notes,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    public function status(HmsTransactionClass $booking): string
    {
        if (! empty($booking->hms_booking_status)) {
            return $booking->hms_booking_status;
        }

        if (! empty($booking->check_out)) {
            return 'checked_out';
        }

        if (! empty($booking->check_in)) {
            return 'checked_in';
        }

        return $this->fromLegacyStatus($booking->status);
    }

    private function applyTransition(
        HmsTransactionClass $booking,
        string $target,
        int $actorId,
        ?string $notes = null
    ): void {
        $current = $this->status($booking);

        if (! in_array($target, self::TRANSITIONS[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => __('hms::lang.invalid_booking_transition', [
                    'from' => $current,
                    'to' => $target,
                ]),
            ]);
        }

        $booking->hms_booking_status = $target;
        $booking->status = in_array($target, ['cancelled', 'no_show'], true)
            ? 'cancelled'
            : ($target === 'tentative' ? 'pending' : 'confirmed');
        $booking->hms_last_status_changed_at = now();
        $booking->save();

        $this->record(
            $booking,
            $target,
            $current,
            $target,
            $actorId,
            $notes
        );
    }

    private function lockedBooking(int $businessId, int $bookingId): HmsTransactionClass
    {
        return HmsTransactionClass::where('business_id', $businessId)
            ->where('type', 'hms_booking')
            ->whereKey($bookingId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function releaseGroupPickup(HmsTransactionClass $booking): void
    {
        $query = HmsGroupRoomBlock::where('business_id', $booking->business_id)
            ->where('transaction_id', $booking->id);
        (clone $query)->whereNotNull('release_at')->where('release_at', '<=', now())
            ->update(['transaction_id' => null, 'status' => 'released']);
        $query->update(['transaction_id' => null, 'status' => 'held']);
    }

    private function assertStatus(HmsTransactionClass $booking, string $expected): void
    {
        $current = $this->status($booking);
        if ($current !== $expected) {
            throw ValidationException::withMessages([
                'status' => __('hms::lang.invalid_booking_transition', [
                    'from' => $current,
                    'to' => $expected === 'reserved' ? 'checked_in' : 'checked_out',
                ]),
            ]);
        }
    }

    private function fromLegacyStatus(?string $status): string
    {
        return match ($status) {
            'pending' => 'tentative',
            'cancelled' => 'cancelled',
            default => 'reserved',
        };
    }
}
