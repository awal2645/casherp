<?php

namespace App\Services;

use App\PropertyLease;
use App\PropertyPaymentDeposit;
use App\PropertyPaymentDepositAllocation;
use App\PropertyRentDue;
use App\PropertyRentPayment;
use Illuminate\Support\Facades\DB;

/**
 * A property payment deposit is an advance payment, not a refundable security
 * deposit. It is applied to rent and follows the normal accounting workflow.
 */
class PropertyPaymentDepositService
{
    public function __construct(private PropertyAccountingPostingService $postingService)
    {
    }

    public function record(PropertyLease $lease, array $input, int $actorId): PropertyPaymentDeposit
    {
        return DB::transaction(function () use ($lease, $input, $actorId) {
            $deposit = PropertyPaymentDeposit::create([
                'business_id' => $lease->business_id,
                'property_lease_id' => $lease->id,
                'contact_id' => $lease->contact_id,
                'amount' => round((float) $input['amount'], 4),
                'applied_amount' => 0,
                'received_on' => $input['received_on'],
                'payment_method' => $input['payment_method'],
                'reference' => $input['reference'] ?? null,
                'notes' => $input['notes'] ?? null,
                'status' => 'available',
                'receipt_account_id' => $input['receipt_account_id'] ?? null,
                'created_by' => $actorId,
            ]);
            $this->postingService->postPaymentDepositReceipt($deposit, $actorId);
            $this->allocateForLease($lease, $actorId);

            return $deposit->fresh(['lease', 'allocations.rentDue']);
        }, 3);
    }

    public function allocateForDue(PropertyRentDue $due, int $actorId): void
    {
        DB::transaction(function () use ($due, $actorId) {
            $lockedDue = PropertyRentDue::where('business_id', $due->business_id)
                ->lockForUpdate()->findOrFail($due->id);
            $this->allocateLockedDue($lockedDue, $actorId);
        }, 3);
    }

    public function allocateForLease(PropertyLease $lease, int $actorId): void
    {
        PropertyRentDue::where('business_id', $lease->business_id)
            ->where('property_lease_id', $lease->id)
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->orderBy('due_date')->orderBy('id')->get()
            ->each(fn (PropertyRentDue $due) => $this->allocateForDue($due, $actorId));
    }

    private function allocateLockedDue(PropertyRentDue $due, int $actorId): void
    {
        $balance = round(max(0, (float) $due->amount_due - (float) $due->amount_paid), 4);
        if ($balance <= 0) {
            return;
        }
        $deposits = PropertyPaymentDeposit::where('business_id', $due->business_id)
            ->where('property_lease_id', $due->property_lease_id)
            ->whereIn('status', ['available', 'partially_applied'])
            ->whereColumn('applied_amount', '<', 'amount')
            ->orderBy('received_on')->orderBy('id')->lockForUpdate()->get();

        foreach ($deposits as $deposit) {
            if ($balance <= 0.0001) {
                break;
            }
            $available = round((float) $deposit->amount - (float) $deposit->applied_amount, 4);
            $amount = min($available, $balance);
            if ($amount <= 0) {
                continue;
            }
            $allocation = PropertyPaymentDepositAllocation::create([
                'property_payment_deposit_id' => $deposit->id,
                'property_rent_due_id' => $due->id,
                'amount' => $amount,
                'created_by' => $actorId,
            ]);
            $payment = PropertyRentPayment::create([
                'business_id' => $due->business_id,
                'property_rent_due_id' => $due->id,
                'amount' => $amount,
                'paid_on' => $deposit->received_on,
                'method' => $deposit->payment_method,
                'receipt_account_id' => $deposit->receipt_account_id,
                'reference' => $deposit->reference ?: 'ADV-'.$deposit->id.'-'.$allocation->id,
                'created_by' => $actorId,
            ]);
            $deposit->applied_amount = round((float) $deposit->applied_amount + $amount, 4);
            $deposit->status = (float) $deposit->applied_amount + 0.0001 >= (float) $deposit->amount
                ? 'applied' : 'partially_applied';
            $deposit->save();
            $due->amount_paid = round((float) $due->amount_paid + $amount, 4);
            $due->status = (float) $due->amount_paid + 0.0001 >= (float) $due->amount_due ? 'paid' : 'partial';
            $due->save();
            $this->postingService->postPaymentDepositApplication($allocation, $actorId);
            $balance = round(max(0, $balance - $amount), 4);
        }
    }
}
