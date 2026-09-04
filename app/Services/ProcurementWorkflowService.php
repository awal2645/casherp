<?php

namespace App\Services;

use App\Events\ExpenseCreatedOrModified;
use App\ProcurementApprovalStep;
use App\ProcurementDocument;
use App\ProcurementEvent;
use App\ProcurementSupplierQuote;
use App\Transaction;
use App\User;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Notifications\ProcurementStatusNotification;

class ProcurementWorkflowService
{
    public const STAGES = [
        1 => ['stage' => 'manager', 'permission' => 'procurement.approve.manager'],
        2 => ['stage' => 'finance', 'permission' => 'procurement.approve.finance'],
        3 => ['stage' => 'admin', 'permission' => 'procurement.approve.admin'],
    ];

    public function __construct(protected TransactionUtil $transactionUtil)
    {
    }

    public function begin(Transaction $transaction, string $documentType, array $attributes = []): ProcurementDocument
    {
        if (! in_array($documentType, ['requisition', 'purchase_order'], true)) {
            throw new \InvalidArgumentException('Unsupported procurement document type.');
        }

        $document = ProcurementDocument::create(array_merge([
            'business_id' => $transaction->business_id,
            'transaction_id' => $transaction->id,
            'document_type' => $documentType,
            'requested_by' => $transaction->created_by,
            'priority' => 'normal',
            'approval_status' => 'pending_manager',
            'current_stage' => 'manager',
            'submitted_at' => now(),
        ], $attributes));

        foreach (self::STAGES as $sequence => $stage) {
            $document->approvals()->create([
                'sequence' => $sequence,
                'stage' => $stage['stage'],
                'required_permission' => $stage['permission'],
                'status' => $sequence === 1 ? 'pending' : 'waiting',
            ]);
        }

        $transaction->status = 'draft';
        $transaction->save();
        $this->recordEvent($document, 'submitted', null, $document->approval_status, $document->requested_by);
        $this->notifyCurrentApproversAfterCommit($document);

        return $document;
    }

    public function approve(ProcurementDocument $document, User $actor, ?string $note = null): ProcurementDocument
    {
        return DB::transaction(function () use ($document, $actor, $note) {
            $document = ProcurementDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($document);
            $this->assertCanAct($document, $actor);

            if ($document->document_type === 'requisition'
                && $document->current_stage === 'finance'
                && empty($document->selected_quote_id)) {
                throw ValidationException::withMessages([
                    'quote' => 'Select a supplier quotation before Finance approval.',
                ]);
            }

            $step = $document->approvals()
                ->where('stage', $document->current_stage)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $document->approval_status;
            $step->update([
                'status' => 'approved',
                'acted_by' => $actor->id,
                'note' => $note,
                'acted_at' => now(),
            ]);

            $next = $document->approvals()
                ->where('sequence', '>', $step->sequence)
                ->orderBy('sequence')
                ->first();

            if ($next) {
                $next->update(['status' => 'pending']);
                $document->update([
                    'approval_status' => 'pending_'.$next->stage,
                    'current_stage' => $next->stage,
                    'lock_version' => DB::raw('lock_version + 1'),
                ]);
                $this->recordEvent($document, 'stage_approved', $fromStatus, $document->approval_status, $actor->id, $note, [
                    'stage' => $step->stage,
                ]);
                $this->notifyCurrentApproversAfterCommit($document);
            } else {
                $document->update([
                    'approval_status' => 'approved',
                    'current_stage' => null,
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'lock_version' => DB::raw('lock_version + 1'),
                ]);
                $document->transaction()->update(['status' => 'ordered']);
                $this->recordEvent($document, 'approved', $fromStatus, 'approved', $actor->id, $note);

                if ($document->document_type === 'requisition') {
                    $purchaseOrder = $this->generatePurchaseOrder($document, $actor);
                    $this->notifyRequesterAfterCommit($document, 'approved', 'Purchase order '.$purchaseOrder->ref_no.' was generated and sent for approval.');
                } else {
                    $expense = $this->createAutomaticExpense($document, $actor);
                    $this->notifyRequesterAfterCommit($document, 'approved', 'Expense '.$expense->ref_no.' was created automatically.');
                }
            }

            return $document->fresh(['transaction', 'approvals.actor', 'selectedQuote.supplier', 'autoExpense']);
        });
    }

    public function reject(ProcurementDocument $document, User $actor, string $note): ProcurementDocument
    {
        return DB::transaction(function () use ($document, $actor, $note) {
            $document = ProcurementDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->assertPending($document);
            $this->assertCanAct($document, $actor);

            $step = $document->approvals()
                ->where('stage', $document->current_stage)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();
            $fromStatus = $document->approval_status;

            $step->update([
                'status' => 'rejected',
                'acted_by' => $actor->id,
                'note' => $note,
                'acted_at' => now(),
            ]);
            $document->update([
                'approval_status' => 'rejected',
                'current_stage' => null,
                'rejected_at' => now(),
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
            $document->transaction()->update(['status' => 'draft']);
            $this->recordEvent($document, 'rejected', $fromStatus, 'rejected', $actor->id, $note, [
                'stage' => $step->stage,
            ]);
            $this->notifyRequesterAfterCommit($document, 'rejected', $note);

            return $document->fresh(['transaction', 'approvals.actor']);
        });
    }

    public function resubmit(ProcurementDocument $document, User $actor, ?string $note = null): ProcurementDocument
    {
        return DB::transaction(function () use ($document, $actor, $note) {
            $document = ProcurementDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->approval_status !== 'rejected') {
                throw ValidationException::withMessages(['workflow' => 'Only a rejected document can be resubmitted.']);
            }
            if ($actor->id !== $document->requested_by && ! $this->isBusinessAdmin($actor, $document->business_id)) {
                abort(403, 'Only the requester or company administrator can resubmit this document.');
            }
            if (! $actor->can('procurement.submit') && ! $this->isBusinessAdmin($actor, $document->business_id)) {
                abort(403, 'You are not allowed to submit procurement documents.');
            }

            $document->approvals()->update([
                'status' => 'waiting', 'acted_by' => null, 'note' => null, 'acted_at' => null,
            ]);
            $document->approvals()->where('sequence', 1)->update(['status' => 'pending']);
            $document->update([
                'approval_status' => 'pending_manager',
                'current_stage' => 'manager',
                'submitted_at' => now(),
                'rejected_at' => null,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
            $this->recordEvent($document, 'resubmitted', 'rejected', 'pending_manager', $actor->id, $note);
            $this->notifyCurrentApproversAfterCommit($document);

            return $document->fresh(['transaction', 'approvals.actor']);
        });
    }

    public function selectQuote(ProcurementDocument $document, ProcurementSupplierQuote $quote, User $actor): ProcurementDocument
    {
        return DB::transaction(function () use ($document, $quote, $actor) {
            $document = ProcurementDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->document_type !== 'requisition'
                || ! in_array($document->approval_status, ['pending_manager', 'pending_finance', 'rejected'], true)) {
                throw ValidationException::withMessages(['quote' => 'The supplier quotation can no longer be changed.']);
            }
            if ($quote->business_id !== $document->business_id
                || $quote->requisition_transaction_id !== $document->transaction_id) {
                abort(404);
            }
            if ($quote->valid_until && $quote->valid_until->lt(today())) {
                throw ValidationException::withMessages(['quote' => 'This supplier quotation has expired.']);
            }
            if (! $actor->can('procurement.quote.manage') && ! $this->isBusinessAdmin($actor, $document->business_id)) {
                abort(403, 'Unauthorized action.');
            }

            ProcurementSupplierQuote::where('requisition_transaction_id', $document->transaction_id)
                ->update(['status' => 'received']);
            $quote->update(['status' => 'selected']);
            $document->update([
                'selected_quote_id' => $quote->id,
                'currency_id' => $quote->currency_id,
                'exchange_rate' => $quote->exchange_rate,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
            $this->recordEvent($document, 'supplier_quote_selected', $document->approval_status, $document->approval_status, $actor->id, null, [
                'quote_id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'total' => $quote->total,
                'currency_id' => $quote->currency_id,
            ]);

            return $document->fresh(['selectedQuote.supplier', 'selectedQuote.currency']);
        });
    }

    public function quoteAdded(ProcurementDocument $document, ProcurementSupplierQuote $quote, User $actor): void
    {
        $this->recordEvent(
            $document,
            'supplier_quote_added',
            $document->approval_status,
            $document->approval_status,
            $actor->id,
            null,
            [
                'quote_id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'total' => $quote->total,
                'currency_id' => $quote->currency_id,
            ]
        );
    }

    public function correctionsSaved(ProcurementDocument $document, User $actor, array $metadata = []): void
    {
        $this->recordEvent(
            $document,
            'corrections_saved',
            $document->approval_status,
            $document->approval_status,
            $actor->id,
            null,
            $metadata
        );
    }

    public function permissionForStage(?string $stage): ?string
    {
        foreach (self::STAGES as $definition) {
            if ($definition['stage'] === $stage) {
                return $definition['permission'];
            }
        }

        return null;
    }

    public function canAct(ProcurementDocument $document, User $actor): bool
    {
        $permission = $this->permissionForStage($document->current_stage);
        if (! $permission) {
            return false;
        }

        if ($actor->id === $document->requested_by && ! $this->isBusinessAdmin($actor, $document->business_id)) {
            return false;
        }

        return $actor->can($permission) || $this->isBusinessAdmin($actor, $document->business_id);
    }

    private function assertPending(ProcurementDocument $document): void
    {
        if (! str_starts_with($document->approval_status, 'pending_') || empty($document->current_stage)) {
            throw ValidationException::withMessages(['workflow' => 'This document is not waiting for approval.']);
        }
    }

    private function assertCanAct(ProcurementDocument $document, User $actor): void
    {
        if (! $this->canAct($document, $actor)) {
            abort(403, 'You are not allowed to complete this approval stage.');
        }
    }

    private function isBusinessAdmin(User $user, int $businessId): bool
    {
        return $user->hasRole('Admin#'.$businessId) || $user->can('superadmin');
    }

    private function generatePurchaseOrder(ProcurementDocument $document, User $actor): Transaction
    {
        $document->loadMissing(['transaction.purchase_lines', 'selectedQuote']);
        $quote = $document->selectedQuote;
        if (! $quote) {
            throw ValidationException::withMessages(['quote' => 'Select a supplier quotation before final approval.']);
        }
        if ($quote->valid_until && $quote->valid_until->lt(today())) {
            throw ValidationException::withMessages(['quote' => 'The selected supplier quotation has expired. Select a valid quotation before final approval.']);
        }

        $existing = ProcurementDocument::where('document_type', 'purchase_order')
            ->where('parent_transaction_id', $document->transaction_id)
            ->first();
        if ($existing) {
            return $existing->transaction;
        }

        $exchangeRate = (float) $quote->exchange_rate;
        $refCount = $this->transactionUtil->setAndGetReferenceCount('purchase_order', $document->business_id);
        $purchaseOrder = Transaction::create([
            'business_id' => $document->business_id,
            'location_id' => $document->transaction->location_id,
            'contact_id' => $quote->supplier_id,
            'type' => 'purchase_order',
            'status' => 'draft',
            'created_by' => $actor->id,
            'transaction_date' => now(),
            'delivery_date' => $quote->delivery_date,
            'ref_no' => $this->transactionUtil->generateReferenceNumber('purchase_order', $refCount, $document->business_id),
            'purchase_requisition_ids' => [$document->transaction_id],
            'exchange_rate' => $exchangeRate,
            'total_before_tax' => (float) $quote->subtotal * $exchangeRate,
            'tax_id' => $quote->tax_id,
            'tax_amount' => (float) $quote->tax_amount * $exchangeRate,
            'shipping_charges' => (float) $quote->shipping_amount * $exchangeRate,
            'final_total' => (float) $quote->total * $exchangeRate,
            'additional_notes' => $document->purpose,
        ]);

        $linePrices = $quote->line_prices ?: [];
        foreach ($document->transaction->purchase_lines as $requisitionLine) {
            $line = $requisitionLine->replicate();
            $unitPrice = (float) ($linePrices[(string) $requisitionLine->id] ?? $linePrices[$requisitionLine->id] ?? 0);
            $line->transaction_id = $purchaseOrder->id;
            $line->purchase_requisition_line_id = $requisitionLine->id;
            $line->purchase_order_line_id = null;
            $line->po_quantity_purchased = 0;
            $line->purchase_price = $unitPrice * $exchangeRate;
            $line->purchase_price_inc_tax = $unitPrice * $exchangeRate;
            $line->item_tax = 0;
            $line->tax_id = null;
            $line->save();

            $requisitionLine->po_quantity_purchased = (float) $requisitionLine->po_quantity_purchased + (float) $requisitionLine->quantity;
            $requisitionLine->save();
        }

        $this->transactionUtil->updatePurchaseOrderStatus([$document->transaction_id]);

        $poAttributes = [
            'parent_transaction_id' => $document->transaction_id,
            'department_id' => $document->department_id,
            'project_id' => $document->project_id,
            'currency_id' => $quote->currency_id,
            'requested_by' => $document->requested_by,
            'priority' => $document->priority,
            'purpose' => $document->purpose,
            'budget_amount' => $quote->total,
            'exchange_rate' => $quote->exchange_rate,
            'selected_quote_id' => $quote->id,
        ];
        $poDocument = $this->begin($purchaseOrder, 'purchase_order', $poAttributes);
        $this->recordEvent($poDocument, 'generated_from_requisition', null, $poDocument->approval_status, $actor->id, null, [
            'requisition_transaction_id' => $document->transaction_id,
            'quote_id' => $quote->id,
        ]);
        $this->transactionUtil->activityLog($purchaseOrder, 'added');

        return $purchaseOrder;
    }

    private function createAutomaticExpense(ProcurementDocument $document, User $actor): Transaction
    {
        $document = ProcurementDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
        if ($document->auto_expense_transaction_id) {
            return Transaction::findOrFail($document->auto_expense_transaction_id);
        }

        $document->loadMissing(['transaction', 'selectedQuote']);
        $purchaseOrder = $document->transaction;
        $quote = $document->selectedQuote;
        $refCount = $this->transactionUtil->setAndGetReferenceCount('expense', $document->business_id);
        $expenseData = [
            'business_id' => $document->business_id,
            'location_id' => $purchaseOrder->location_id,
            'contact_id' => $purchaseOrder->contact_id,
            'type' => 'expense',
            'status' => 'final',
            'payment_status' => 'due',
            'created_by' => $actor->id,
            'transaction_date' => now(),
            'exchange_rate' => $purchaseOrder->exchange_rate ?: 1,
            'ref_no' => $this->transactionUtil->generateReferenceNumber('expense', $refCount, $document->business_id),
            'total_before_tax' => $purchaseOrder->total_before_tax,
            'tax_id' => $purchaseOrder->tax_id,
            'tax_amount' => $purchaseOrder->tax_amount,
            'final_total' => $purchaseOrder->final_total,
            'expense_title' => 'Approved purchase order '.$purchaseOrder->ref_no,
            'payment_to' => optional($purchaseOrder->contact)->name,
            'additional_notes' => trim('Automatically created from approved PO '.$purchaseOrder->ref_no.'. '.$document->purpose),
            'document' => $quote?->attachment ?: $purchaseOrder->document,
            'source' => 'procurement_po',
        ];
        if (Schema::hasColumn('transactions', 'procurement_document_id')) {
            $expenseData['procurement_document_id'] = $document->id;
            $expenseData['procurement_department_id'] = $document->department_id;
            $expenseData['procurement_currency_id'] = $document->currency_id;
        }
        if ($document->project_id && Schema::hasColumn('transactions', 'pjt_project_id')) {
            $expenseData['pjt_project_id'] = $document->project_id;
        }

        $expense = Transaction::create($expenseData);
        $document->update([
            'auto_expense_transaction_id' => $expense->id,
            'lock_version' => DB::raw('lock_version + 1'),
        ]);
        $this->recordEvent($document, 'expense_created', 'approved', 'approved', $actor->id, null, [
            'expense_transaction_id' => $expense->id,
            'expense_ref_no' => $expense->ref_no,
        ]);
        $this->transactionUtil->activityLog($expense, 'added');
        event(new ExpenseCreatedOrModified($expense));

        return $expense;
    }

    private function recordEvent(
        ProcurementDocument $document,
        string $event,
        ?string $fromStatus,
        ?string $toStatus,
        ?int $userId,
        ?string $note = null,
        array $metadata = []
    ): ProcurementEvent {
        return ProcurementEvent::create([
            'procurement_document_id' => $document->id,
            'business_id' => $document->business_id,
            'user_id' => $userId,
            'event' => $event,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function notifyCurrentApproversAfterCommit(ProcurementDocument $document): void
    {
        $documentId = $document->id;
        DB::afterCommit(function () use ($documentId) {
            $fresh = ProcurementDocument::with('transaction')->find($documentId);
            if (! $fresh || ! $fresh->current_stage) {
                return;
            }
            $permission = $this->permissionForStage($fresh->current_stage);
            $users = User::whereHas('roles', fn ($query) => $query->where('roles.business_id', $fresh->business_id))
                ->get()
                ->filter(fn (User $user) => $user->hasRole('Admin#'.$fresh->business_id) || ($permission && $user->can($permission)));
            if ($users->isNotEmpty()) {
                Notification::send($users, new ProcurementStatusNotification($fresh, 'approval_required'));
            }
        });
    }

    private function notifyRequesterAfterCommit(ProcurementDocument $document, string $event, string $message): void
    {
        $requesterId = $document->requested_by;
        $documentId = $document->id;
        DB::afterCommit(function () use ($requesterId, $documentId, $event, $message) {
            $requester = User::find($requesterId);
            $fresh = ProcurementDocument::with('transaction')->find($documentId);
            if ($requester && $fresh) {
                $requester->notify(new ProcurementStatusNotification($fresh, $event, $message));
            }
        });
    }
}
