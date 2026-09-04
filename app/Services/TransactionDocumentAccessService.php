<?php

namespace App\Services;

use App\Transaction;
use App\User;

/**
 * A single authorization boundary for every human-facing sales document.
 *
 * Legacy controllers historically implemented slightly different checks for
 * the modal, print endpoint, PDF endpoint and public-link modal. Keeping the
 * decision here prevents a user from bypassing an "own sale" or location rule
 * merely by changing the output URL.
 */
class TransactionDocumentAccessService
{
    public function resolve(int $businessId, int $transactionId, User $user, string $action = 'preview'): Transaction
    {
        $transaction = Transaction::where('business_id', $businessId)->findOrFail($transactionId);
        $this->authorize($transaction, $user, $businessId, $action);

        return $transaction;
    }

    public function authorize(Transaction $transaction, User $user, int $businessId, string $action = 'preview'): void
    {
        abort_unless((int) $transaction->business_id === $businessId, 404);
        abort_unless(in_array($transaction->type, ['sell', 'sales_order'], true), 404);
        abort_unless($this->locationAllowed($transaction, $user, $businessId), 403, 'This transaction is outside your permitted locations.');
        abort_unless($this->canView($transaction, $user, $businessId), 403, 'You are not authorized to view this transaction document.');

        $isAdmin = $this->isBusinessAdmin($user, $businessId);
        if (in_array($action, ['print', 'download'], true)) {
            abort_unless($isAdmin || $user->canForBusiness('print_invoice', $businessId), 403, 'You are not authorized to print or download this document.');
        }
        if ($action === 'share') {
            abort_unless($isAdmin || $user->canForBusiness('sell.document.share', $businessId), 403, 'You are not authorized to share this document.');
            abort_unless($this->isShareable($transaction), 422, 'Only a quotation, pro-forma, order, or final invoice can be shared.');
        }
        if ($action === 'edit') {
            $this->authorizeEdit($transaction, $user, $businessId, $isAdmin);
        }
    }

    public function canView(Transaction $transaction, User $user, int $businessId): bool
    {
        if ($this->isBusinessAdmin($user, $businessId)) {
            return true;
        }

        $own = (int) $transaction->created_by === (int) $user->id;
        if ($transaction->type === 'sales_order') {
            return $user->canForBusiness('so.view_all', $businessId)
                || ($own && $user->canForBusiness('so.view_own', $businessId));
        }

        if ($this->isQuotation($transaction)) {
            return $user->canForBusiness('quotation.view_all', $businessId)
                || ($own && $user->canForBusiness('quotation.view_own', $businessId))
                || $this->hasGeneralSaleView($user, $businessId, $own);
        }

        if ($transaction->status === 'draft') {
            return $user->canForBusiness('draft.view_all', $businessId)
                || ($own && $user->canForBusiness('draft.view_own', $businessId))
                || $this->hasGeneralSaleView($user, $businessId, $own);
        }

        return $this->hasGeneralSaleView($user, $businessId, $own)
            || ($user->canForBusiness('view_commission_agent_sell', $businessId)
                && (int) $transaction->commission_agent === (int) $user->id);
    }

    public function isShareable(Transaction $transaction): bool
    {
        return in_array($transaction->type, ['sell', 'sales_order'], true)
            && ($transaction->status === 'final'
                || $transaction->type === 'sales_order'
                || $this->isQuotation($transaction));
    }

    private function authorizeEdit(Transaction $transaction, User $user, int $businessId, bool $isAdmin): void
    {
        if ($transaction->type === 'sales_order') {
            abort_unless($isAdmin || $user->canForBusiness('so.update', $businessId), 403, 'You are not authorized to edit this sales order.');

            return;
        }

        if ($this->isQuotation($transaction)) {
            abort_unless($isAdmin || $user->canForBusiness('quotation.update', $businessId), 403, 'You are not authorized to edit this quotation.');

            return;
        }

        if ($transaction->status === 'draft') {
            abort_unless($isAdmin || $user->canForBusiness('draft.update', $businessId), 403, 'You are not authorized to edit this draft.');

            return;
        }

        abort_unless(
            $isAdmin
                || $user->canForBusiness('sell.update', $businessId)
                || $user->canForBusiness('direct_sell.update', $businessId)
                || $user->canForBusiness('direct_sell.access', $businessId)
                || $user->canForBusiness('edit_pos_payment', $businessId)
                || $user->canForBusiness('repair.update', $businessId),
            403,
            'You are not authorized to edit this sale.'
        );
    }

    private function hasGeneralSaleView(User $user, int $businessId, bool $own): bool
    {
        return collect(['sell.view', 'direct_sell.view', 'direct_sell.access'])
            ->contains(fn ($permission) => $user->canForBusiness($permission, $businessId))
            || ($own && $user->canForBusiness('view_own_sell_only', $businessId));
    }

    private function isQuotation(Transaction $transaction): bool
    {
        return (bool) $transaction->is_quotation
            || in_array($transaction->sub_status, ['quotation', 'proforma'], true);
    }

    private function locationAllowed(Transaction $transaction, User $user, int $businessId): bool
    {
        if (! $transaction->location_id) {
            return true;
        }

        $permitted = $user->permitted_locations($businessId);

        return $permitted === 'all'
            || in_array((int) $transaction->location_id, array_map('intval', (array) $permitted), true);
    }

    private function isBusinessAdmin(User $user, int $businessId): bool
    {
        return $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
    }
}
