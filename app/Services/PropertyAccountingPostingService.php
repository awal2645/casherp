<?php

namespace App\Services;

use App\PropertyAccountMapping;
use App\PropertyAccountingPosting;
use App\PropertyMaintenanceTicket;
use App\PropertyPaymentDeposit;
use App\PropertyPaymentDepositAllocation;
use App\PropertyRentDue;
use App\PropertyRentPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountsTransaction;

class PropertyAccountingPostingService
{
    public function postPaymentDepositReceipt(
        PropertyPaymentDeposit $deposit,
        ?int $userId = null
    ): ?PropertyAccountingPosting {
        $mapping = $this->enabledMapping((int) $deposit->business_id);
        if ($mapping === null) {
            return null;
        }

        $this->requireMapping(
            $deposit->receipt_account_id,
            'Choose the Cash or Bank account that received this Payment Deposit (Advance).'
        );
        $this->requireMapping(
            $mapping->payment_advance_liability_account_id,
            'Choose a Customer Advances liability account in Property Accounting Settings.'
        );

        return $this->postSource(
            (int) $deposit->business_id,
            'payment_deposit_receipt',
            (int) $deposit->id,
            (float) $deposit->amount,
            (int) $deposit->receipt_account_id,
            (int) $mapping->payment_advance_liability_account_id,
            $deposit->received_on,
            $userId,
            'Property Payment Deposit (Advance) receipt #'.$deposit->id
        );
    }

    public function postPaymentDepositApplication(
        PropertyPaymentDepositAllocation $allocation,
        ?int $userId = null
    ): ?PropertyAccountingPosting {
        $allocation->loadMissing(['deposit', 'rentDue']);
        $deposit = $allocation->deposit;
        $due = $allocation->rentDue;
        if (! $deposit || ! $due
            || (int) $deposit->business_id !== (int) $due->business_id) {
            throw ValidationException::withMessages([
                'accounting' => 'The Payment Deposit (Advance) allocation is not linked to a valid company rent due.',
            ]);
        }

        $mapping = $this->enabledMapping((int) $deposit->business_id);
        if ($mapping === null) {
            return null;
        }

        // A company may enable automatic posting after the advance was
        // collected but before it is applied. Ensure the original receipt is
        // present before reclassifying the liability.
        $this->postPaymentDepositReceipt($deposit, $userId);
        $this->postRentDue($due, $userId);
        $this->requireMapping(
            $mapping->payment_advance_liability_account_id,
            'Choose a Customer Advances liability account in Property Accounting Settings.'
        );
        $this->requireMapping(
            $mapping->tenant_receivable_account_id,
            'Choose a tenant receivable account in Property Accounting Settings.'
        );

        return $this->postSource(
            (int) $deposit->business_id,
            'payment_deposit_allocation',
            (int) $allocation->id,
            (float) $allocation->amount,
            (int) $mapping->payment_advance_liability_account_id,
            (int) $mapping->tenant_receivable_account_id,
            $due->due_date,
            $userId,
            'Apply Property Payment Deposit (Advance) #'.$deposit->id.' to rent due #'.$due->id
        );
    }

    public function postRentDue(PropertyRentDue $due, ?int $userId = null): ?PropertyAccountingPosting
    {
        $mapping = $this->enabledMapping((int) $due->business_id);

        if ($mapping === null) {
            return null;
        }

        $this->requireMapping(
            $mapping->tenant_receivable_account_id,
            'Choose a tenant receivable account in Property Accounting Settings.'
        );
        $this->requireMapping(
            $mapping->rental_income_account_id,
            'Choose a rental income account in Property Accounting Settings.'
        );

        return $this->postSource(
            (int) $due->business_id,
            'rent_due',
            (int) $due->id,
            (float) $due->amount_due,
            (int) $mapping->tenant_receivable_account_id,
            (int) $mapping->rental_income_account_id,
            $due->due_date,
            $userId,
            'Property rent due #'.$due->id
        );
    }

    public function postRentPayment(
        PropertyRentPayment $payment,
        ?int $userId = null
    ): ?PropertyAccountingPosting {
        $mapping = $this->enabledMapping((int) $payment->business_id);

        if ($mapping === null) {
            return null;
        }

        $due = $payment->due;
        if ($due === null || (int) $due->business_id !== (int) $payment->business_id) {
            throw ValidationException::withMessages([
                'accounting' => 'The rent payment is not linked to a valid rent due.',
            ]);
        }

        // Recognise the rent receivable before clearing it with a receipt.
        $this->postRentDue($due, $userId);

        $this->requireMapping(
            $payment->receipt_account_id,
            'Choose the cash or bank receipt account for this rent payment.'
        );
        $this->requireMapping(
            $mapping->tenant_receivable_account_id,
            'Choose a tenant receivable account in Property Accounting Settings.'
        );

        return $this->postSource(
            (int) $payment->business_id,
            'rent_payment',
            (int) $payment->id,
            (float) $payment->amount,
            (int) $payment->receipt_account_id,
            (int) $mapping->tenant_receivable_account_id,
            $payment->paid_on,
            $userId,
            'Property rent payment #'.$payment->id
        );
    }

    public function postMaintenanceCost(
        PropertyMaintenanceTicket $ticket,
        ?int $userId = null
    ): ?PropertyAccountingPosting {
        if ((float) $ticket->actual_cost <= 0) {
            return null;
        }

        $mapping = $this->enabledMapping((int) $ticket->business_id);

        if ($mapping === null) {
            return null;
        }

        $this->requireMapping(
            $mapping->maintenance_expense_account_id,
            'Choose a maintenance expense account in Property Accounting Settings.'
        );
        $this->requireMapping(
            $ticket->payment_account_id,
            'Choose the cash, bank, or payable account used for this maintenance cost.'
        );

        return $this->postSource(
            (int) $ticket->business_id,
            'maintenance_cost',
            (int) $ticket->id,
            (float) $ticket->actual_cost,
            (int) $mapping->maintenance_expense_account_id,
            (int) $ticket->payment_account_id,
            $ticket->resolved_on ?: now(),
            $userId,
            'Property maintenance ticket #'.$ticket->id
        );
    }

    public function reverse(
        PropertyAccountingPosting $posting,
        ?int $userId = null
    ): PropertyAccountingPosting {
        return DB::transaction(function () use ($posting, $userId) {
            $original = PropertyAccountingPosting::whereKey($posting->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingReversal = PropertyAccountingPosting::where(
                'reversal_of_id',
                $original->id
            )->first();

            if ($existingReversal !== null) {
                return $existingReversal;
            }

            if ($original->status !== 'posted') {
                throw ValidationException::withMessages([
                    'accounting' => 'Only a posted Property Accounting entry can be reversed.',
                ]);
            }

            $debitEntry = AccountingAccountsTransaction::find($original->debit_entry_id);
            $creditEntry = AccountingAccountsTransaction::find($original->credit_entry_id);

            if ($debitEntry === null || $creditEntry === null) {
                throw ValidationException::withMessages([
                    'accounting' => 'The linked Accounting entries are missing; no reversal was made.',
                ]);
            }

            $actor = $userId ?: 0;
            $operationDate = now();
            $note = 'Reversal of property posting #'.$original->id;

            $reversalDebit = $this->createEntry(
                (int) $original->credit_account_id,
                (float) $original->amount,
                'debit',
                'property_reversal_debit',
                $operationDate,
                $actor,
                $note
            );
            $reversalCredit = $this->createEntry(
                (int) $original->debit_account_id,
                (float) $original->amount,
                'credit',
                'property_reversal_credit',
                $operationDate,
                $actor,
                $note
            );

            $reversal = PropertyAccountingPosting::create([
                'business_id' => $original->business_id,
                'source_type' => 'reversal',
                'source_id' => $original->id,
                'debit_account_id' => $original->credit_account_id,
                'credit_account_id' => $original->debit_account_id,
                'amount' => $original->amount,
                'status' => 'posted',
                'debit_entry_id' => $reversalDebit->id,
                'credit_entry_id' => $reversalCredit->id,
                'reversal_of_id' => $original->id,
                'note' => $note,
                'posted_at' => now(),
                'posted_by' => $actor,
            ]);

            $original->update(['status' => 'reversed']);

            return $reversal;
        });
    }

    private function postSource(
        int $businessId,
        string $sourceType,
        int $sourceId,
        float $amount,
        int $debitAccountId,
        int $creditAccountId,
        $operationDate,
        ?int $userId,
        string $note
    ): PropertyAccountingPosting {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'accounting' => 'Accounting entries must have an amount greater than zero.',
            ]);
        }

        $this->accountForBusiness($businessId, $debitAccountId);
        $this->accountForBusiness($businessId, $creditAccountId);

        return DB::transaction(function () use (
            $businessId,
            $sourceType,
            $sourceId,
            $amount,
            $debitAccountId,
            $creditAccountId,
            $operationDate,
            $userId,
            $note
        ) {
            PropertyAccountingPosting::firstOrCreate(
                [
                    'business_id' => $businessId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'debit_account_id' => $debitAccountId,
                    'credit_account_id' => $creditAccountId,
                    'amount' => $amount,
                    'status' => 'pending',
                    'note' => $note,
                ]
            );

            $posting = PropertyAccountingPosting::where('business_id', $businessId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($posting->status, ['posted', 'reversed'], true)) {
                return $posting;
            }

            if ($posting->debit_entry_id || $posting->credit_entry_id) {
                throw ValidationException::withMessages([
                    'accounting' => 'An incomplete Accounting posting already exists and needs review.',
                ]);
            }

            $actor = $userId ?: 0;
            $date = $operationDate instanceof Carbon
                ? $operationDate
                : Carbon::parse($operationDate);

            $debitEntry = $this->createEntry(
                $debitAccountId,
                $amount,
                'debit',
                'property_'.$sourceType.'_debit',
                $date,
                $actor,
                $note
            );
            $creditEntry = $this->createEntry(
                $creditAccountId,
                $amount,
                'credit',
                'property_'.$sourceType.'_credit',
                $date,
                $actor,
                $note
            );

            $posting->update([
                'debit_account_id' => $debitAccountId,
                'credit_account_id' => $creditAccountId,
                'amount' => $amount,
                'status' => 'posted',
                'debit_entry_id' => $debitEntry->id,
                'credit_entry_id' => $creditEntry->id,
                'note' => $note,
                'posted_at' => now(),
                'posted_by' => $actor,
            ]);

            return $posting->fresh();
        });
    }

    private function createEntry(
        int $accountId,
        float $amount,
        string $type,
        string $mapType,
        $operationDate,
        int $userId,
        string $note
    ): AccountingAccountsTransaction {
        return AccountingAccountsTransaction::create([
            'accounting_account_id' => $accountId,
            'transaction_id' => null,
            'transaction_payment_id' => null,
            'amount' => $amount,
            'type' => $type,
            'sub_type' => 'property_management',
            'map_type' => $mapType,
            'operation_date' => $operationDate,
            'created_by' => $userId,
            'note' => $note,
        ]);
    }

    private function enabledMapping(int $businessId): ?PropertyAccountMapping
    {
        return PropertyAccountMapping::where('business_id', $businessId)
            ->where('auto_post_enabled', true)
            ->first();
    }

    private function accountForBusiness(int $businessId, int $accountId): AccountingAccount
    {
        $account = AccountingAccount::where('business_id', $businessId)
            ->where('status', 'active')
            ->find($accountId);

        if ($account === null) {
            throw ValidationException::withMessages([
                'accounting' => 'An Accounting account is inactive or belongs to another business.',
            ]);
        }

        return $account;
    }

    private function requireMapping($value, string $message): void
    {
        if (empty($value)) {
            throw ValidationException::withMessages(['accounting' => $message]);
        }
    }
}
