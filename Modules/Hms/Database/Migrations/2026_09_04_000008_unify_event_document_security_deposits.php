<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Earlier document builds could create one refundable ledger for every A4
     * document generated from the same HMS event. Consolidate those ledgers
     * under the actual event without losing receipts, damage, refunds or audit
     * evidence. The highest configured requirement is retained; monetary entry
     * rows are moved, never recreated.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hms_event_bookings')
            || ! Schema::hasTable('business_documents')
            || ! Schema::hasTable('security_deposits')
            || ! Schema::hasTable('security_deposit_entries')) {
            return;
        }

        DB::table('business_documents')
            ->where('source_type', 'hms_event_booking')
            ->whereNotNull('source_id')
            ->select(['id', 'business_id', 'source_id'])
            ->orderBy('id')
            ->chunkById(100, function ($documents) {
                foreach ($documents as $document) {
                    $event = DB::table('hms_event_bookings')
                        ->where('business_id', $document->business_id)
                        ->where('id', $document->source_id)
                        ->first();
                    if (! $event) {
                        continue;
                    }

                    $source = DB::table('security_deposits')
                        ->where('business_id', $document->business_id)
                        ->where('context_type', 'event_document')
                        ->where('context_id', $document->id)
                        ->first();
                    if (! $source) {
                        continue;
                    }

                    DB::transaction(function () use ($document, $event, $source) {
                        $target = DB::table('security_deposits')
                            ->where('business_id', $document->business_id)
                            ->where('context_type', 'hms_event')
                            ->where('context_id', $event->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $target) {
                            DB::table('security_deposits')->where('id', $source->id)->update([
                                'context_type' => 'hms_event',
                                'context_id' => $event->id,
                                'business_location_id' => $event->location_id ?: $source->business_location_id,
                                'contact_id' => $event->contact_id ?: $source->contact_id,
                                'due_date' => $event->starts_at ? substr((string) $event->starts_at, 0, 10) : $source->due_date,
                                'updated_at' => now(),
                            ]);
                            $targetId = $source->id;
                        } else {
                            DB::table('security_deposit_entries')
                                ->where('security_deposit_id', $source->id)
                                ->update(['security_deposit_id' => $target->id, 'updated_at' => now()]);
                            DB::table('security_deposits')->where('id', $target->id)->update([
                                'required_amount' => max((float) $target->required_amount, (float) $source->required_amount),
                                'business_location_id' => $event->location_id ?: ($target->business_location_id ?: $source->business_location_id),
                                'contact_id' => $event->contact_id ?: ($target->contact_id ?: $source->contact_id),
                                'due_date' => $event->starts_at ? substr((string) $event->starts_at, 0, 10) : ($target->due_date ?: $source->due_date),
                                'updated_at' => now(),
                            ]);
                            DB::table('security_deposits')->where('id', $source->id)->delete();
                            $targetId = $target->id;
                        }

                        $this->refreshStatus($targetId);
                    }, 3);
                }
            }, 'id');
    }

    /**
     * This is an accounting-data repair. Re-splitting one real event ledger
     * into arbitrary per-document ledgers would be unsafe, so rollback is a
     * deliberate no-op.
     */
    public function down(): void
    {
    }

    private function refreshStatus(int $depositId): void
    {
        $deposit = DB::table('security_deposits')->where('id', $depositId)->first();
        if (! $deposit) {
            return;
        }

        $active = DB::table('security_deposit_entries')
            ->where('security_deposit_id', $depositId)
            ->where('status', 'active')
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'receipt' THEN amount ELSE 0 END), 0) AS received")
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'damage_charge' THEN amount ELSE 0 END), 0) AS damage")
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'refund' THEN amount ELSE 0 END), 0) AS refunded")
            ->first();
        $pendingRefund = (float) DB::table('security_deposit_entries')
            ->where('security_deposit_id', $depositId)
            ->where('entry_type', 'refund')
            ->whereIn('status', ['pending_approval', 'approved'])
            ->sum('amount');
        $received = (float) $active->received;
        $damage = (float) $active->damage;
        $refunded = (float) $active->refunded;
        $held = max(0, $received - $damage - $refunded);
        $excessDamage = max(0, $damage - $received);

        if ($deposit->status === 'waived' && (float) $deposit->required_amount <= 0.0001 && $received <= 0.0001) {
            $status = 'waived';
        } elseif ($excessDamage > 0.0001) {
            $status = 'excess_due';
        } elseif ($pendingRefund > 0.0001) {
            $status = 'refund_pending';
        } elseif ($received <= 0.0001) {
            $status = 'pending';
        } elseif ($received + 0.0001 < (float) $deposit->required_amount) {
            $status = 'partially_held';
        } elseif ($held <= 0.0001) {
            $status = 'settled';
        } else {
            $status = $damage > 0.0001 ? 'damage_assessed' : 'held';
        }

        DB::table('security_deposits')->where('id', $depositId)->update([
            'status' => $status,
            'settled_at' => $status === 'settled' ? ($deposit->settled_at ?: now()) : null,
            'updated_at' => now(),
        ]);
    }
};
