<?php

namespace Modules\Hms\Services;

use App\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsDepositSchedule;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsFolioEntry;
use Modules\Hms\Entities\HmsGroupBooking;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsTransactionClass;

class FolioService
{
    public function ensureForEvent(\Modules\Hms\Entities\HmsEventBooking $event, int $actorId): HmsFolio
    {
        $event = \Modules\Hms\Entities\HmsEventBooking::where('business_id', $event->business_id)
            ->whereKey($event->id)->lockForUpdate()->firstOrFail();
        $existing = HmsFolio::where('business_id', $event->business_id)
            ->where('hms_event_booking_id', $event->id)->first();
        if ($existing) {
            return $existing;
        }

        $property = HmsProperty::where('business_id', $event->business_id)->findOrFail($event->hms_property_id);
        $business = Business::findOrFail($event->business_id);
        $folio = HmsFolio::create([
            'business_id' => $event->business_id,
            'hms_property_id' => $event->hms_property_id,
            'hms_event_booking_id' => $event->id,
            'contact_id' => $event->contact_id,
            'folio_number' => 'PENDING-EVENT-'.$event->id.'-'.uniqid(),
            'folio_type' => 'event',
            'currency_id' => $property->currency_id ?: $business->currency_id,
            'exchange_rate' => 1,
            'status' => 'open',
            'opened_at' => now(),
            'created_by' => $actorId,
        ]);
        $folio->folio_number = sprintf('EF-%d-%s-%06d', $event->business_id, now()->format('ym'), $folio->id);
        $folio->save();

        return $folio;
    }

    public function ensureForGroup(HmsGroupBooking $group, int $actorId): HmsFolio
    {
        $group = HmsGroupBooking::where('business_id', $group->business_id)
            ->whereKey($group->id)
            ->lockForUpdate()
            ->firstOrFail();
        $existing = HmsFolio::where('business_id', $group->business_id)
            ->where('hms_group_booking_id', $group->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $property = HmsProperty::where('business_id', $group->business_id)->findOrFail($group->hms_property_id);
        $business = Business::findOrFail($group->business_id);
        $folio = HmsFolio::create([
            'business_id' => $group->business_id,
            'hms_property_id' => $group->hms_property_id,
            'hms_group_booking_id' => $group->id,
            'contact_id' => $group->organizer_contact_id,
            'folio_number' => 'PENDING-GROUP-' . $group->id . '-' . uniqid(),
            'folio_type' => 'master',
            'currency_id' => $property->currency_id ?: $business->currency_id,
            'exchange_rate' => 1,
            'status' => 'open',
            'opened_at' => now(),
            'created_by' => $actorId,
        ]);
        $folio->folio_number = sprintf('GF-%d-%s-%06d', $group->business_id, now()->format('ym'), $folio->id);
        $folio->save();

        return $folio;
    }

    public function ensureForBooking(HmsTransactionClass $booking, int $actorId): HmsFolio
    {
        $booking = HmsTransactionClass::where('business_id', $booking->business_id)
            ->where('type', 'hms_booking')
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();
        $propertyId = (int) ($booking->hms_property_id ?: $booking->hms_booking_lines()->value('hms_property_id'));
        $property = HmsProperty::where('business_id', $booking->business_id)->findOrFail($propertyId);
        $folio = HmsFolio::where('business_id', $booking->business_id)
            ->where('transaction_id', $booking->id)
            ->where('folio_type', 'guest')
            ->first();

        if ($folio) {
            return $folio;
        }

        $business = Business::findOrFail($booking->business_id);
        $folio = HmsFolio::create([
            'business_id' => $booking->business_id,
            'hms_property_id' => $property->id,
            'transaction_id' => $booking->id,
            'contact_id' => $booking->contact_id,
            'hms_guest_profile_id' => $booking->hms_guest_profile_id,
            'folio_number' => 'PENDING-' . $booking->id . '-' . uniqid(),
            'folio_type' => 'guest',
            'currency_id' => $property->currency_id ?: $business->currency_id,
            'exchange_rate' => 1,
            'status' => 'open',
            'opened_at' => now(),
            'created_by' => $actorId,
        ]);
        $folio->folio_number = sprintf('F-%d-%s-%06d', $booking->business_id, now()->format('ym'), $folio->id);
        $folio->save();

        return $folio;
    }

    public function syncBooking(HmsTransactionClass $booking, int $actorId): HmsFolio
    {
        return DB::transaction(function () use ($booking, $actorId) {
            $folio = $this->ensureForBooking($booking, $actorId);
            $folio = HmsFolio::whereKey($folio->id)->lockForUpdate()->firstOrFail();
            $roomCharge = round((float) $booking->hms_booking_lines()->sum('total_price'), 4);
            $extraCharge = 0.0;
            if (Schema::hasTable('hms_booking_extras')) {
                $extraQuery = $booking->hms_booking_extras();
                if (Schema::hasColumn('hms_booking_extras', 'financial_classification')) {
                    $extraQuery->where('financial_classification', 'revenue');
                }
                $extraCharge = round((float) $extraQuery->sum('price'), 4);
            }
            $netAdjustment = round((float) $booking->final_total - $roomCharge - $extraCharge, 4);
            $this->syncBookingComponent($folio, $booking->id, 'room_charge', $roomCharge, $actorId);
            $this->syncBookingComponent($folio, $booking->id, 'extra_charge', $extraCharge, $actorId);
            // Net tax and discount are kept outside room revenue so ADR and
            // RevPAR are not inflated by extras, tax, or promotional effects.
            $this->syncBookingComponent($folio, $booking->id, 'booking_adjustment', $netAdjustment, $actorId);

            $paid = round((float) $booking->payment_lines()
                ->where(function ($query) {
                    $query->whereNull('is_return')->orWhere('is_return', false);
                })
                ->where(function ($query) {
                    $query->whereNull('payment_purpose')->orWhere('payment_purpose', '!=', 'security_deposit');
                })
                ->sum('amount'), 4);
            $returns = round((float) $booking->payment_lines()
                ->where('is_return', true)
                ->where(function ($query) {
                    $query->whereNull('payment_purpose')->orWhere('payment_purpose', '!=', 'security_deposit');
                })
                ->sum('amount'), 4);
            $targetPaid = max(0, $paid - $returns);
            $currentPaid = (float) HmsFolioEntry::where('hms_folio_id', $folio->id)
                ->where('category', 'booking_payment')
                ->whereIn('status', ['posted', 'approved'])
                ->get()->sum(fn ($entry) => $entry->direction === 'credit' ? (float) $entry->amount : -(float) $entry->amount);
            $paymentDelta = round($targetPaid - $currentPaid, 4);

            if (abs($paymentDelta) >= 0.0001) {
                $this->post($folio, [
                    'entry_type' => 'payment',
                    'category' => 'booking_payment',
                    'direction' => $paymentDelta >= 0 ? 'credit' : 'debit',
                    'amount' => abs($paymentDelta),
                    'description' => __('hms::lang.booking_payment_sync'),
                    'idempotency_key' => 'booking-payment:' . $booking->id
                        . ':from:' . number_format($currentPaid, 4, '.', '')
                        . ':to:' . number_format($targetPaid, 4, '.', ''),
                ], $actorId);
            }

            $this->refreshStatus($folio);

            return $folio->fresh('entries');
        });
    }

    public function post(HmsFolio $folio, array $input, int $actorId): HmsFolioEntry
    {
        if (! in_array($folio->status, ['open', 'settled'], true)) {
            throw ValidationException::withMessages(['folio' => __('hms::lang.closed_folio_cannot_change')]);
        }

        $amount = round((float) ($input['amount'] ?? 0), 4);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('validation.gt.numeric', ['attribute' => 'amount', 'value' => 0])]);
        }

        $property = HmsProperty::where('business_id', $folio->business_id)->findOrFail($folio->hms_property_id);
        $rate = max(0.00000001, (float) ($input['exchange_rate'] ?? $folio->exchange_rate ?? 1));
        $status = ($input['entry_type'] ?? null) === 'refund' ? 'pending' : 'posted';
        $key = $input['idempotency_key'] ?? null;

        $attributes = [
            'business_id' => $folio->business_id,
            'hms_folio_id' => $folio->id,
            'linked_entry_id' => $input['linked_entry_id'] ?? null,
            'transaction_payment_id' => $input['transaction_payment_id'] ?? null,
            'entry_type' => $input['entry_type'],
            'category' => $input['category'],
            'direction' => $input['direction'],
            'amount' => $amount,
            'base_amount' => round($amount * $rate, 4),
            'tax_amount' => round((float) ($input['tax_amount'] ?? 0), 4),
            'exchange_rate' => $rate,
            'payment_method' => $input['payment_method'] ?? null,
            'business_date' => $property->business_date,
            'posted_at' => now(),
            'status' => $status,
            'description' => $input['description'] ?? null,
            'idempotency_key' => $key,
            'metadata' => $input['metadata'] ?? null,
            'created_by' => $actorId,
        ];
        $entry = $key
            ? HmsFolioEntry::firstOrCreate([
                'business_id' => $folio->business_id,
                'idempotency_key' => $key,
            ], collect($attributes)->except(['business_id', 'idempotency_key'])->all())
            : HmsFolioEntry::create($attributes);

        $this->refreshStatus($folio);

        return $entry;
    }

    public function approveRefund(int $businessId, int $entryId, int $actorId): HmsFolioEntry
    {
        return DB::transaction(function () use ($businessId, $entryId, $actorId) {
            $entry = HmsFolioEntry::where('business_id', $businessId)->where('entry_type', 'refund')->lockForUpdate()->findOrFail($entryId);
            if ($entry->status !== 'pending') {
                throw ValidationException::withMessages(['entry' => __('hms::lang.refund_not_pending')]);
            }
            $entry->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
            $this->refreshStatus($entry->folio);

            return $entry->fresh();
        });
    }

    public function transfer(
        int $businessId,
        int $entryId,
        int $targetFolioId,
        float $amount,
        string $idempotencyToken,
        int $actorId
    ): array {
        return DB::transaction(function () use ($businessId, $entryId, $targetFolioId, $amount, $idempotencyToken, $actorId) {
            $sourceEntry = HmsFolioEntry::where('business_id', $businessId)
                ->whereIn('status', ['posted', 'approved'])
                ->lockForUpdate()
                ->findOrFail($entryId);
            $folioIds = [(int) $sourceEntry->hms_folio_id, $targetFolioId];
            sort($folioIds);
            $folios = HmsFolio::where('business_id', $businessId)
                ->whereIn('id', $folioIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $sourceFolio = $folios->get((int) $sourceEntry->hms_folio_id);
            $targetFolio = $folios->get($targetFolioId);
            if (! $sourceFolio || ! $targetFolio || ! in_array($targetFolio->status, ['open', 'settled'], true)) {
                throw ValidationException::withMessages(['target_folio_id' => __('hms::lang.invalid_target_folio')]);
            }

            if ($sourceFolio->id === $targetFolio->id) {
                throw ValidationException::withMessages(['target_folio_id' => __('hms::lang.folio_transfer_same_folio')]);
            }
            if ((int) $sourceFolio->currency_id !== (int) $targetFolio->currency_id) {
                throw ValidationException::withMessages(['target_folio_id' => __('hms::lang.folio_transfer_currency_mismatch')]);
            }

            $outKey = 'folio-transfer-out:' . $idempotencyToken;
            $inKey = 'folio-transfer-in:' . $idempotencyToken;
            $existingOut = HmsFolioEntry::where('business_id', $businessId)->where('idempotency_key', $outKey)->first();
            $existingIn = HmsFolioEntry::where('business_id', $businessId)->where('idempotency_key', $inKey)->first();
            if ($existingOut && $existingIn) {
                return ['source' => $existingOut, 'target' => $existingIn];
            }

            $amount = round($amount, 4);
            $alreadyTransferred = (float) HmsFolioEntry::where('business_id', $businessId)
                ->where('hms_folio_id', $sourceFolio->id)
                ->where('linked_entry_id', $sourceEntry->id)
                ->where('category', 'transfer')
                ->whereIn('status', ['posted', 'approved'])
                ->sum('amount');
            $remaining = round((float) $sourceEntry->amount - $alreadyTransferred, 4);
            if ($amount <= 0 || $amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => __('hms::lang.folio_transfer_amount_invalid', ['amount' => number_format(max(0, $remaining), 4, '.', '')]),
                ]);
            }

            $sourceDirection = $sourceEntry->direction === 'debit' ? 'credit' : 'debit';
            $common = [
                'entry_type' => 'adjustment',
                'category' => 'transfer',
                'amount' => $amount,
                'linked_entry_id' => $sourceEntry->id,
                'metadata' => ['transfer_token' => $idempotencyToken],
            ];
            $out = $this->post($sourceFolio, $common + [
                'direction' => $sourceDirection,
                'description' => __('hms::lang.folio_transfer_out', ['folio' => $targetFolio->folio_number]),
                'idempotency_key' => $outKey,
            ], $actorId);
            $in = $this->post($targetFolio, $common + [
                'direction' => $sourceEntry->direction,
                'description' => __('hms::lang.folio_transfer_in', ['folio' => $sourceFolio->folio_number]),
                'idempotency_key' => $inKey,
            ], $actorId);
            $out->update(['metadata' => ['transfer_token' => $idempotencyToken, 'counterpart_entry_id' => $in->id]]);
            $in->update(['metadata' => ['transfer_token' => $idempotencyToken, 'counterpart_entry_id' => $out->id]]);

            return ['source' => $out->fresh(), 'target' => $in->fresh()];
        });
    }

    public function void(int $businessId, int $entryId, string $reason, int $actorId): HmsFolioEntry
    {
        return DB::transaction(function () use ($businessId, $entryId, $reason, $actorId) {
            $entry = HmsFolioEntry::where('business_id', $businessId)->lockForUpdate()->findOrFail($entryId);
            if ($entry->status === 'void') {
                return $entry;
            }
            if ($entry->category !== 'transfer' && HmsFolioEntry::where('business_id', $businessId)
                ->where('hms_folio_id', $entry->hms_folio_id)
                ->where('linked_entry_id', $entry->id)
                ->where('category', 'transfer')
                ->whereIn('status', ['posted', 'approved'])
                ->exists()) {
                throw ValidationException::withMessages(['entry' => __('hms::lang.transferred_entry_cannot_be_voided')]);
            }
            if ($entry->category === 'transfer' && ! empty($entry->metadata['counterpart_entry_id'])) {
                $pair = HmsFolioEntry::where('business_id', $businessId)
                    ->whereIn('id', [$entry->id, (int) $entry->metadata['counterpart_entry_id']])
                    ->lockForUpdate()
                    ->get();
                foreach ($pair as $transferEntry) {
                    if ($transferEntry->status !== 'void') {
                        $transferEntry->update(['status' => 'void', 'voided_by' => $actorId, 'voided_at' => now(), 'void_reason' => $reason]);
                    }
                }
                foreach ($pair->pluck('hms_folio_id')->unique() as $folioId) {
                    $this->refreshStatus(HmsFolio::where('business_id', $businessId)->findOrFail($folioId));
                }

                return HmsFolioEntry::findOrFail($entry->id);
            }
            $entry->update(['status' => 'void', 'voided_by' => $actorId, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->refreshStatus($entry->folio);

            return $entry->fresh();
        });
    }

    public function scheduleDeposit(HmsFolio $folio, float $amount, string $dueDate, int $actorId, ?string $notes = null): HmsDepositSchedule
    {
        return HmsDepositSchedule::create([
            'business_id' => $folio->business_id,
            'hms_folio_id' => $folio->id,
            'amount' => round($amount, 4),
            'due_date' => $dueDate,
            'status' => 'due',
            'purpose' => 'payment_deposit',
            'notes' => $notes,
            'created_by' => $actorId,
        ]);
    }

    public function refreshStatus(HmsFolio $folio): void
    {
        $balance = $folio->fresh('entries')->balance;
        $folio->status = abs($balance) < 0.0001 ? 'settled' : 'open';
        $folio->closed_at = $folio->status === 'settled' && optional($folio->booking)->hms_booking_status === 'checked_out' ? now() : null;
        $folio->save();
    }

    private function syncBookingComponent(
        HmsFolio $folio,
        int $bookingId,
        string $category,
        float $target,
        int $actorId
    ): void {
        $current = (float) HmsFolioEntry::where('hms_folio_id', $folio->id)
            ->where('category', $category)
            ->whereIn('status', ['posted', 'approved'])
            ->get()
            ->sum(fn ($entry) => $entry->direction === 'debit'
                ? (float) $entry->amount
                : -(float) $entry->amount);
        $delta = round($target - $current, 4);
        if (abs($delta) < 0.0001) {
            return;
        }

        $this->post($folio, [
            'entry_type' => abs($current) < 0.0001 && $delta > 0 ? 'charge' : 'adjustment',
            'category' => $category,
            'direction' => $delta >= 0 ? 'debit' : 'credit',
            'amount' => abs($delta),
            'description' => __('hms::lang.booking_component_sync', [
                'component' => __('hms::lang.'.$category),
            ]),
            'idempotency_key' => 'booking-component:' . $bookingId . ':' . $category
                . ':from:' . number_format($current, 4, '.', '')
                . ':to:' . number_format($target, 4, '.', ''),
        ], $actorId);
    }
}
