<?php

namespace App\Services;

use App\CashRegister;
use App\Restaurant\RegisterCount;
use App\Restaurant\RegisterReconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterReconciliationService
{
    public function calculateExpectedCash(CashRegister $register): float
    {
        $core = (float) DB::table('cash_register_transactions')
            ->where('cash_register_id', $register->id)
            ->where('pay_method', 'cash')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE -amount END), 0) AS expected")
            ->value('expected');
        $movements = (float) DB::table('restaurant_register_movements')
            ->where('cash_register_id', $register->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'cash_in' THEN amount ELSE -amount END), 0) AS movement_total")
            ->value('movement_total');

        return round($core + $movements, 4);
    }

    public function submit(int $businessId, int $registerId, array $counts, ?string $varianceReason, int $actorId): RegisterReconciliation
    {
        return DB::transaction(function () use ($businessId, $registerId, $counts, $varianceReason, $actorId) {
            $register = CashRegister::where('business_id', $businessId)->whereKey($registerId)->lockForUpdate()->firstOrFail();
            $expected = $this->calculateExpectedCash($register);
            $counted = round(collect($counts)->sum(fn ($row) => abs((float) $row['denomination']) * max(0, (int) $row['quantity'])), 4);
            $variance = round($counted - $expected, 4);
            if (abs($variance) > 0.0001 && trim((string) $varianceReason) === '') {
                throw ValidationException::withMessages(['variance_reason' => 'Explain every cash variance before submission.']);
            }

            $reconciliation = RegisterReconciliation::updateOrCreate(
                ['cash_register_id' => $register->id],
                [
                    'business_id' => $businessId,
                    'location_id' => $register->location_id,
                    'expected_cash' => $expected,
                    'counted_cash' => $counted,
                    'variance' => $variance,
                    'status' => 'pending_approval',
                    'variance_reason' => $varianceReason,
                    'submitted_by' => $actorId,
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ]
            );
            $reconciliation->counts()->delete();
            foreach ($counts as $row) {
                $denomination = abs((float) $row['denomination']);
                $quantity = max(0, (int) $row['quantity']);
                RegisterCount::create([
                    'reconciliation_id' => $reconciliation->id,
                    'denomination' => $denomination,
                    'quantity' => $quantity,
                    'amount' => round($denomination * $quantity, 4),
                ]);
            }

            return $reconciliation->fresh('counts');
        }, 3);
    }

    public function review(int $businessId, int $reconciliationId, string $decision, ?string $note, int $actorId): RegisterReconciliation
    {
        return DB::transaction(function () use ($businessId, $reconciliationId, $decision, $note, $actorId) {
            $reconciliation = RegisterReconciliation::where('business_id', $businessId)->whereKey($reconciliationId)->lockForUpdate()->firstOrFail();
            if ($reconciliation->status !== 'pending_approval') {
                throw ValidationException::withMessages(['status' => 'Only a pending register reconciliation can be reviewed.']);
            }
            if ((int) $reconciliation->submitted_by === $actorId) {
                throw ValidationException::withMessages(['status' => 'A different authorized user must review this register.']);
            }
            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be approved or rejected.']);
            }
            if ($decision === 'rejected' && trim((string) $note) === '') {
                throw ValidationException::withMessages(['review_note' => 'Explain why this reconciliation was rejected.']);
            }

            $reconciliation->update([
                'status' => $decision,
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return $reconciliation->fresh('counts');
        }, 3);
    }
}
