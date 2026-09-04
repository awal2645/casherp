<?php

namespace Modules\Hms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsEventCharge;
use Modules\Hms\Entities\HmsEventVenue;

class EventBookingService
{
    public function __construct(protected FolioService $folios)
    {
    }

    public function create(int $businessId, array $input, int $actorId): HmsEventBooking
    {
        return DB::transaction(function () use ($businessId, $input, $actorId) {
            $venue = HmsEventVenue::where('business_id', $businessId)->where('is_active', true)->lockForUpdate()->findOrFail($input['hms_event_venue_id']);
            if ((int) $venue->hms_property_id !== (int) $input['hms_property_id']) {
                throw ValidationException::withMessages(['hms_event_venue_id' => 'The venue does not belong to the selected property.']);
            }
            if ($venue->capacity && (int) $input['expected_guests'] > (int) $venue->capacity) {
                throw ValidationException::withMessages(['expected_guests' => 'Expected attendance exceeds this venue capacity.']);
            }

            $conflict = HmsEventBooking::where('business_id', $businessId)
                ->where('hms_event_venue_id', $venue->id)
                ->whereIn('status', ['tentative', 'confirmed', 'in_progress'])
                ->where('starts_at', '<', $input['ends_at'])
                ->where('ends_at', '>', $input['starts_at'])
                ->lockForUpdate()->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'This venue is already held or booked during the selected period.']);
            }

            $event = HmsEventBooking::create($input + [
                'business_id' => $businessId,
                'location_id' => $venue->property->location_id,
                'event_number' => 'PENDING-'.uniqid(),
                'status' => 'tentative',
                'created_by' => $actorId,
            ]);
            $event->event_number = sprintf('EV-%d-%s-%06d', $businessId, now()->format('ym'), $event->id);
            $event->save();
            $folio = $this->folios->ensureForEvent($event, $actorId);
            if ((float) $event->agreed_amount > 0) {
                $entry = $this->folios->post($folio, [
                    'entry_type' => 'charge',
                    'category' => 'event_package',
                    'direction' => 'debit',
                    'amount' => (float) $event->agreed_amount,
                    'description' => 'Event package: '.$event->event_name,
                    'idempotency_key' => 'event-package:'.$event->id,
                ], $actorId);
                HmsEventCharge::create([
                    'business_id' => $businessId,
                    'hms_event_booking_id' => $event->id,
                    'hms_folio_entry_id' => $entry->id,
                    'category' => 'event_package',
                    'description' => 'Event package: '.$event->event_name,
                    'amount' => $event->agreed_amount,
                    'direction' => 'debit',
                    'idempotency_key' => 'event-package:'.$event->id,
                    'created_by' => $actorId,
                ]);
            }

            return $event->fresh(['venue', 'property', 'folio.entries']);
        });
    }

    public function transition(int $businessId, int $eventId, string $status): HmsEventBooking
    {
        return DB::transaction(function () use ($businessId, $eventId, $status) {
            $event = HmsEventBooking::where('business_id', $businessId)->lockForUpdate()->findOrFail($eventId);
            $transitions = [
                'tentative' => ['confirmed', 'cancelled'],
                'confirmed' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed'],
                'completed' => [],
                'cancelled' => [],
            ];
            if (! in_array($status, $transitions[$event->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Event cannot move from {$event->status} to {$status}."]);
            }
            if ($status === 'completed') {
                app(\App\Services\SecurityDepositService::class)->assertContextCanClose($businessId, 'hms_event', $event->id);
            }
            $event->update(['status' => $status]);

            return $event->fresh();
        });
    }

    public function addCharge(int $businessId, int $eventId, array $input, int $actorId): HmsEventCharge
    {
        return DB::transaction(function () use ($businessId, $eventId, $input, $actorId) {
            $event = HmsEventBooking::where('business_id', $businessId)->lockForUpdate()->findOrFail($eventId);
            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages(['event' => 'Charges cannot be posted to a cancelled event.']);
            }
            $direction = in_array($input['category'], ['payment_deposit', 'discount'], true) ? 'credit' : 'debit';
            if ($input['category'] === 'payment_deposit' && empty(trim((string) ($input['payment_method'] ?? '')))) {
                throw ValidationException::withMessages(['payment_method' => 'Select how the payment deposit was received.']);
            }
            $folio = $this->folios->ensureForEvent($event, $actorId);
            $key = 'event-charge:'.$event->id.':'.$input['idempotency_token'];
            $entry = $this->folios->post($folio, [
                'entry_type' => $input['category'] === 'payment_deposit'
                    ? 'payment'
                    : ($input['category'] === 'discount' ? 'adjustment' : 'charge'),
                'category' => $input['category'],
                'direction' => $direction,
                'amount' => $input['amount'],
                'payment_method' => $input['payment_method'] ?? null,
                'description' => $input['description'],
                'idempotency_key' => $key,
            ], $actorId);

            return HmsEventCharge::firstOrCreate(
                ['business_id' => $businessId, 'idempotency_key' => $key],
                [
                    'hms_event_booking_id' => $event->id,
                    'hms_folio_entry_id' => $entry->id,
                    'category' => $input['category'],
                    'description' => $input['description'],
                    'amount' => $input['amount'],
                    'direction' => $direction,
                    'created_by' => $actorId,
                ]
            );
        });
    }
}
