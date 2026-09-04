<?php

namespace App\Services;

use App\Business;
use App\BusinessDocument;
use App\PropertyLease;
use App\SecurityDeposit;
use App\SecurityDepositEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsTransactionClass;

/**
 * Refundable security deposits are intentionally isolated from invoices,
 * revenue, tax and the accounting ledger. Only a separately documented damage
 * charge may later be posted by the relevant billing workflow.
 */
class SecurityDepositService
{
    public function forPropertyLease(PropertyLease $lease, ?float $requiredAmount = null, ?int $actorId = null): ?SecurityDeposit
    {
        $lease->loadMissing(['unit.property', 'tenant']);
        $required = $requiredAmount ?? (float) $lease->security_deposit;
        if ($required <= 0 && ! $this->find((int) $lease->business_id, 'property_lease', (int) $lease->id)) {
            return null;
        }

        $business = Business::findOrFail($lease->business_id);

        return $this->ensure([
            'business_id' => (int) $lease->business_id,
            'business_location_id' => optional(optional($lease->unit)->property)->business_location_id,
            'context_type' => 'property_lease',
            'context_id' => (int) $lease->id,
            'contact_id' => $lease->contact_id,
            'currency_id' => $business->currency_id,
            'required_amount' => max(0, $required),
            'due_date' => optional($lease->start_date)->toDateString(),
            'terms' => 'Refundable after the unit is inspected, less documented damage or an authorized breach deduction.',
        ], $actorId);
    }

    public function forHmsFolio(HmsFolio $folio, ?float $requiredAmount = null, ?int $actorId = null): ?SecurityDeposit
    {
        $folio->loadMissing(['property', 'booking', 'eventBooking', 'contact']);
        if (! $folio->booking && ! $folio->eventBooking) {
            throw ValidationException::withMessages(['folio' => 'A guest booking or event venue booking must be selected before a security deposit can be created.']);
        }
        $context = $folio->eventBooking
            ? ['context_type' => 'hms_event', 'context_id' => (int) $folio->hms_event_booking_id]
            : ['context_type' => 'hms_booking', 'context_id' => (int) $folio->transaction_id];
        $contextType = $context['context_type'];
        $contextId = $context['context_id'];
        $existing = $this->find((int) $folio->business_id, $contextType, $contextId);
        if (($requiredAmount ?? 0) <= 0 && ! $existing) {
            return null;
        }

        return $this->ensure([
            'business_id' => (int) $folio->business_id,
            'business_location_id' => optional($folio->property)->location_id,
            'contact_id' => $folio->contact_id,
            'currency_id' => $folio->currency_id,
            'required_amount' => max(0, $requiredAmount ?? (float) optional($existing)->required_amount),
            'due_date' => $folio->eventBooking
                ? optional($folio->eventBooking->starts_at)->toDateString()
                : (! empty($folio->booking->hms_booking_arrival_date_time)
                    ? \Carbon\Carbon::parse($folio->booking->hms_booking_arrival_date_time)->toDateString()
                    : now()->toDateString()),
            'terms' => $folio->eventBooking
                ? 'Refundable after the hired venue is inspected, less documented damage, loss or an authorized contract deduction.'
                : 'Refundable after checkout inspection, less documented loss, damage or an authorized breach deduction.',
        ] + $context, $actorId);
    }

    public function forEventDocument(BusinessDocument $document, ?float $requiredAmount = null, ?int $actorId = null): ?SecurityDeposit
    {
        $context = $this->eventDocumentContext($document);
        $existing = $this->find(
            (int) $document->business_id,
            $context['context_type'],
            (int) $context['context_id']
        );
        if (($requiredAmount ?? 0) <= 0 && ! $existing) {
            return null;
        }

        return $this->ensure([
            'business_id' => (int) $document->business_id,
            'business_location_id' => $context['business_location_id'],
            'context_type' => $context['context_type'],
            'context_id' => $context['context_id'],
            'contact_id' => $context['contact_id'],
            'currency_id' => $document->currency_id,
            'required_amount' => max(0, $requiredAmount ?? (float) optional($existing)->required_amount),
            'due_date' => $context['due_date'],
            'terms' => 'Refundable after the hired venue is inspected, less documented damage or an authorized contract deduction.',
        ], $actorId);
    }

    /**
     * Documents derived from one HMS event share the event's single refundable
     * deposit ledger. A standalone venue/property document remains its own
     * operational context. This prevents a quotation, contract and invoice for
     * the same event from each appearing to require a separate deposit.
     */
    public function eventDocumentContext(BusinessDocument $document): array
    {
        $document->loadMissing('type');
        if ($document->scenario_code !== 'event') {
            throw ValidationException::withMessages(['document' => 'Security deposits from this screen are limited to event and venue-hire documents.']);
        }
        if (! $document->asset_type || ! $document->asset_id) {
            throw ValidationException::withMessages(['asset_id' => 'Select the actual property unit, hotel event venue, or responsible operating location before adding a deposit.']);
        }

        if ($document->source_type === 'hms_event_booking' && $document->source_id) {
            $event = HmsEventBooking::where('business_id', $document->business_id)
                ->with('property')
                ->findOrFail((int) $document->source_id);

            return [
                'context_type' => 'hms_event',
                'context_id' => (int) $event->id,
                'business_location_id' => $event->location_id ?: optional($event->property)->location_id,
                'contact_id' => $event->contact_id,
                'due_date' => optional($event->starts_at)->toDateString()
                    ?: optional($document->service_start_at)->toDateString()
                    ?: optional($document->issue_date)->toDateString(),
            ];
        }

        return [
            'context_type' => 'event_document',
            'context_id' => (int) $document->id,
            'business_location_id' => $document->location_id,
            'contact_id' => $document->contact_id,
            'due_date' => optional($document->service_start_at)->toDateString()
                ?: optional($document->issue_date)->toDateString(),
        ];
    }

    public function findForEventDocument(BusinessDocument $document): ?SecurityDeposit
    {
        $context = $this->eventDocumentContext($document);

        return $this->find(
            (int) $document->business_id,
            $context['context_type'],
            (int) $context['context_id']
        );
    }

    public function assertEventDocumentCanClose(BusinessDocument $document): void
    {
        $context = $this->eventDocumentContext($document);
        $this->assertContextCanClose(
            (int) $document->business_id,
            $context['context_type'],
            (int) $context['context_id']
        );
    }

    public function ensure(array $attributes, ?int $actorId): SecurityDeposit
    {
        return DB::transaction(function () use ($attributes, $actorId) {
            $deposit = SecurityDeposit::forBusiness((int) $attributes['business_id'])
                ->where('context_type', $attributes['context_type'])
                ->where('context_id', $attributes['context_id'])
                ->lockForUpdate()
                ->first();
            if (! $deposit) {
                return SecurityDeposit::create($attributes + [
                    'status' => 'pending',
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ])->fresh('entries');
            }
            if ((float) $attributes['required_amount'] + 0.0001 < $deposit->received_amount) {
                throw ValidationException::withMessages(['required_amount' => 'The required amount cannot be lower than the amount already held.']);
            }
            $deposit->update(collect($attributes)->except(['business_id', 'context_type', 'context_id'])->all() + ['updated_by' => $actorId]);

            return $this->refreshStatus($deposit);
        }, 3);
    }

    public function receipt(SecurityDeposit $deposit, array $input, int $actorId): SecurityDepositEntry
    {
        return $this->post($deposit, 'receipt', $input, $actorId, 'active');
    }

    public function damage(SecurityDeposit $deposit, array $input, int $actorId): SecurityDepositEntry
    {
        $deposit->load('entries');
        if ($deposit->pending_refund_amount > 0.0001) {
            throw ValidationException::withMessages(['damage' => 'A refund is already awaiting approval. Void that refund request before recording a new damage assessment.']);
        }
        if (empty(trim((string) ($input['description'] ?? '')))) {
            throw ValidationException::withMessages(['description' => 'Describe the damaged or lost item and the assessment basis.']);
        }

        return $this->post($deposit, 'damage_charge', $input, $actorId, 'active');
    }

    public function requestRefund(SecurityDeposit $deposit, array $input, int $actorId): SecurityDepositEntry
    {
        $deposit->load('entries');
        if ((float) $input['amount'] > $deposit->available_refund + 0.0001) {
            throw ValidationException::withMessages(['amount' => 'The refund cannot exceed the currently refundable security-deposit balance.']);
        }

        return $this->post($deposit, 'refund', $input, $actorId, 'pending_approval');
    }

    public function approveRefund(SecurityDepositEntry $entry, int $actorId): SecurityDepositEntry
    {
        return DB::transaction(function () use ($entry, $actorId) {
            $locked = SecurityDepositEntry::where('business_id', $entry->business_id)
                ->where('entry_type', 'refund')->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages(['refund' => 'Only a pending security-deposit refund can be approved.']);
            }
            if ((int) $locked->created_by === $actorId) {
                throw ValidationException::withMessages([
                    'refund' => 'The person who requested this security-deposit refund cannot approve it. A different authorized user must review and approve the refund.',
                ]);
            }
            $locked->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
            $this->refreshStatus($locked->deposit);

            return $locked->fresh();
        }, 3);
    }

    public function payRefund(SecurityDepositEntry $entry, int $actorId): SecurityDepositEntry
    {
        return DB::transaction(function () use ($entry, $actorId) {
            $locked = SecurityDepositEntry::where('business_id', $entry->business_id)
                ->where('entry_type', 'refund')->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'refund' => 'Only an approved security-deposit refund can be marked as paid.',
                ]);
            }
            $locked->update([
                'status' => 'active',
                'paid_by' => $actorId,
                'paid_at' => now(),
            ]);
            $this->refreshStatus($locked->deposit);

            return $locked->fresh();
        }, 3);
    }

    public function void(SecurityDepositEntry $entry, string $reason, int $actorId): SecurityDepositEntry
    {
        return DB::transaction(function () use ($entry, $reason, $actorId) {
            $locked = SecurityDepositEntry::where('business_id', $entry->business_id)->lockForUpdate()->findOrFail($entry->id);
            if ($locked->status === 'void') {
                return $locked;
            }
            $locked->update(['status' => 'void', 'voided_by' => $actorId, 'voided_at' => now(), 'void_reason' => $reason]);
            $this->refreshStatus($locked->deposit);

            return $locked->fresh();
        }, 3);
    }

    public function assertContextCanClose(int $businessId, string $contextType, int $contextId): void
    {
        $deposit = $this->find($businessId, $contextType, $contextId);
        if (! $deposit) {
            return;
        }
        $deposit->load('entries');
        if ($deposit->status === 'excess_due' && $this->excessDamageIsResolved($deposit)) {
            $deposit->update(['status' => 'settled', 'settled_at' => now()]);
            $deposit->refresh();
        }
        if (! in_array($deposit->status, ['settled', 'waived'], true)) {
            throw ValidationException::withMessages([
                'security_deposit' => 'Clear the refundable security deposit before checkout or closure. Record damage, approve and pay the refund, or waive an uncollected requirement.',
            ]);
        }
    }

    public function waiveUncollected(SecurityDeposit $deposit, string $reason, int $actorId): SecurityDeposit
    {
        $deposit->load('entries');
        if ($deposit->received_amount > 0.0001) {
            throw ValidationException::withMessages(['security_deposit' => 'A deposit that has been received cannot be waived; settle or refund it instead.']);
        }
        $deposit->update(['required_amount' => 0, 'status' => 'waived', 'settled_at' => now(), 'terms' => trim($deposit->terms."\nWaiver: ".$reason), 'updated_by' => $actorId]);

        return $deposit->fresh('entries');
    }

    public function alertsForBusiness(int $businessId, ?array $contextTypes = null): Collection
    {
        return SecurityDeposit::forBusiness($businessId)
            ->when($contextTypes, fn ($query) => $query->whereIn('context_type', $contextTypes))
            ->whereNotIn('status', ['settled', 'waived'])
            ->with(['entries', 'contact', 'location'])
            ->orderBy('due_date')
            ->get()
            ->map(function (SecurityDeposit $deposit) {
                $context = $this->contextSummary($deposit);
                $collectionShortfall = $deposit->received_amount + 0.0001 < (float) $deposit->required_amount;
                $collectionOverdue = $collectionShortfall && $deposit->due_date && $deposit->due_date->isPast();
                $mustSettle = in_array($deposit->status, ['refund_pending', 'excess_due'], true)
                    || $collectionOverdue
                    || $context['closing'];
                if (! $mustSettle) {
                    return null;
                }

                $reason = 'Security deposit must be cleared at transaction closure';
                $severity = 'warning';
                if ($deposit->status === 'refund_pending') {
                    $reason = 'Refund awaiting approval or payment';
                } elseif ($deposit->status === 'excess_due') {
                    $reason = 'Damage exceeds the held security deposit';
                    $severity = 'danger';
                } elseif ($collectionOverdue) {
                    $reason = 'Security deposit collection is overdue';
                    $severity = 'danger';
                } elseif ($deposit->status === 'partially_held') {
                    $reason = 'Security deposit is only partly collected';
                }

                return [
                    'deposit' => $deposit,
                    'reason' => $reason,
                    'severity' => $severity,
                    'transaction_label' => $context['label'],
                    'transaction_date' => $context['date'],
                ];
            })
            ->filter()
            ->values();
    }

    public function find(int $businessId, string $contextType, int $contextId): ?SecurityDeposit
    {
        return SecurityDeposit::forBusiness($businessId)
            ->where('context_type', $contextType)->where('context_id', $contextId)
            ->with('entries')->first();
    }

    private function post(SecurityDeposit $deposit, string $type, array $input, int $actorId, string $status): SecurityDepositEntry
    {
        return DB::transaction(function () use ($deposit, $type, $input, $actorId, $status) {
            $locked = SecurityDeposit::forBusiness($deposit->business_id)->lockForUpdate()->findOrFail($deposit->id);
            $locked->load('entries');
            $amount = round((float) ($input['amount'] ?? 0), 4);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'The amount must be greater than zero.']);
            }
            if ($type === 'receipt' && $locked->received_amount + $amount > (float) $locked->required_amount + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'The receipt cannot exceed the configured refundable security deposit. Increase the requirement first if the agreement changed.']);
            }
            if ($type === 'refund' && $amount > $locked->available_refund + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund cannot exceed the currently refundable security-deposit balance after other pending requests.',
                ]);
            }
            $key = $input['idempotency_key'] ?? null;
            $attributes = [
                'security_deposit_id' => $locked->id,
                'business_id' => $locked->business_id,
                'entry_type' => $type,
                'amount' => $amount,
                'payment_method' => $input['payment_method'] ?? null,
                'reference' => $input['reference'] ?? null,
                'description' => $input['description'] ?? null,
                'occurred_on' => $input['occurred_on'] ?? now()->toDateString(),
                'evidence_path' => $input['evidence_path'] ?? null,
                'business_document_id' => $input['business_document_id'] ?? null,
                'idempotency_key' => $key,
                'status' => $status,
                'created_by' => $actorId,
                'metadata' => $input['metadata'] ?? null,
            ];
            $entry = $key
                ? SecurityDepositEntry::firstOrCreate(
                    ['business_id' => $locked->business_id, 'idempotency_key' => $key],
                    collect($attributes)->except(['business_id', 'idempotency_key'])->all()
                )
                : SecurityDepositEntry::create($attributes);
            $this->refreshStatus($locked);

            return $entry;
        }, 3);
    }

    private function refreshStatus(SecurityDeposit $deposit): SecurityDeposit
    {
        $deposit = $deposit->fresh('entries');
        if ($deposit->status === 'waived' && (float) $deposit->required_amount <= 0.0001 && $deposit->received_amount <= 0.0001) {
            return $deposit;
        }
        if ($deposit->excess_damage_amount > 0.0001) {
            $status = 'excess_due';
        } elseif ($deposit->pending_refund_amount > 0.0001) {
            $status = 'refund_pending';
        } elseif ($deposit->received_amount <= 0.0001) {
            $status = 'pending';
        } elseif ($deposit->received_amount + 0.0001 < (float) $deposit->required_amount) {
            $status = 'partially_held';
        } elseif ($deposit->held_balance <= 0.0001) {
            $status = 'settled';
        } else {
            $status = $deposit->damage_amount > 0 ? 'damage_assessed' : 'held';
        }
        $deposit->update(['status' => $status, 'settled_at' => $status === 'settled' ? now() : null]);

        return $deposit->fresh('entries');
    }

    /**
     * Alerts must identify the exact operational transaction and only become
     * persistent settlement alerts when that lease, stay or event is closing.
     */
    private function contextSummary(SecurityDeposit $deposit): array
    {
        if ($deposit->context_type === 'property_lease') {
            $lease = PropertyLease::where('business_id', $deposit->business_id)->find($deposit->context_id);

            return [
                'label' => $lease ? 'Lease #'.$lease->id : 'Lease #'.$deposit->context_id,
                'date' => optional(optional($lease)->end_date)->toDateString(),
                'closing' => $lease && ($lease->status === 'ended' || ($lease->end_date && $lease->end_date->endOfDay()->isPast())),
            ];
        }

        if ($deposit->context_type === 'hms_booking') {
            $booking = HmsTransactionClass::where('business_id', $deposit->business_id)->find($deposit->context_id);
            $departure = $booking && $booking->hms_booking_departure_date_time
                ? \Carbon\Carbon::parse($booking->hms_booking_departure_date_time)
                : null;

            return [
                'label' => $booking ? 'Booking '.($booking->invoice_no ?: '#'.$booking->id) : 'Booking #'.$deposit->context_id,
                'date' => optional($departure)->toDateString(),
                'closing' => $booking && ($booking->hms_booking_status === 'checked_out' || ($departure && $departure->isPast())),
            ];
        }

        if ($deposit->context_type === 'hms_event') {
            $event = HmsEventBooking::where('business_id', $deposit->business_id)->find($deposit->context_id);
            $end = optional($event)->ends_at;

            return [
                'label' => $event ? 'Event '.$event->event_number : 'Hotel event #'.$deposit->context_id,
                'date' => optional($end)->toDateString(),
                'closing' => $event && (in_array($event->status, ['completed', 'cancelled'], true) || ($end && $end->isPast())),
            ];
        }

        $document = BusinessDocument::forBusiness((int) $deposit->business_id)->find($deposit->context_id);
        $end = optional($document)->service_end_at;

        return [
            'label' => $document ? $document->document_number : 'Event document #'.$deposit->context_id,
            'date' => optional($end)->toDateString(),
            'closing' => $document && (in_array($document->status, ['completed', 'void'], true) || ($end && $end->isPast())),
        ];
    }

    private function excessDamageIsResolved(SecurityDeposit $deposit): bool
    {
        if ($deposit->held_balance > 0.0001 || $deposit->pending_refund_amount > 0.0001) {
            return false;
        }

        $damages = $deposit->entries->where('entry_type', 'damage_charge')->where('status', 'active');
        if ($damages->isEmpty()) {
            return false;
        }

        if (in_array($deposit->context_type, ['hms_booking', 'hms_event'], true)) {
            $folio = HmsFolio::where('business_id', $deposit->business_id)
                ->when($deposit->context_type === 'hms_booking', fn ($query) => $query->where('transaction_id', $deposit->context_id))
                ->when($deposit->context_type === 'hms_event', fn ($query) => $query->where('hms_event_booking_id', $deposit->context_id))
                ->with('entries')
                ->first();

            return $folio && $damages->every(fn ($entry) => ! empty($entry->hms_folio_entry_id))
                && $folio->balance <= 0.0001;
        }

        return $damages->every(function ($entry) use ($deposit) {
            if (empty($entry->business_document_id)) {
                return false;
            }
            $document = BusinessDocument::forBusiness((int) $deposit->business_id)
                ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
                ->find($entry->business_document_id);

            return $document && (float) $document->balance_due <= 0.0001;
        });
    }
}
