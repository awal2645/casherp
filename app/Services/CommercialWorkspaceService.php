<?php

namespace App\Services;

use App\Business;
use App\BusinessDocument;
use App\BusinessDocumentPayment;
use App\BusinessLocation;
use App\Contact;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommercialWorkspaceService
{
    private const VIEWS = [
        'overview', 'invoices', 'orders', 'quotations', 'drafts', 'returns',
        'fulfilment', 'payments', 'documents', 'customers',
    ];

    public function payload(Request $request, array $filters): array
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        abort_if($businessId < 1 || ! $user->canAccessBusiness($businessId), 403);

        $business = Business::with(['industry:id,name,code', 'currency:id,code,symbol'])
            ->findOrFail($businessId);
        $permissions = $this->permissions($user, $businessId);
        $availableViews = collect(self::VIEWS)
            ->filter(fn ($view) => $this->viewAllowed($view, $permissions))
            ->values();
        abort_if($availableViews->isEmpty(), 403, 'Your role does not include commercial workspace access.');

        $requestedView = $filters['view'] ?? null;
        if ($requestedView && ! $availableViews->contains($requestedView)) {
            abort(403, 'Your role does not include this commercial workspace section.');
        }
        $view = $requestedView ?: $availableViews->first();
        $locations = $this->locations($user, $businessId);
        $locationId = isset($filters['location_id']) ? (int) $filters['location_id'] : null;
        if ($locationId && ! $locations->contains('id', $locationId)) {
            abort(403, 'This location is outside your assigned company access.');
        }

        $types = $this->documentTypes($business, $user, $permissions);

        return [
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'csrf_token' => csrf_token(),
            'company' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => optional($business->industry)->only(['name', 'code']),
            ],
            'currency' => [
                'code' => optional($business->currency)->code,
                'symbol' => optional($business->currency)->symbol,
            ],
            'selected_view' => $view,
            'available_views' => $availableViews,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'status' => $filters['status'] ?? '',
                'document_type_id' => isset($filters['document_type_id']) ? (int) $filters['document_type_id'] : null,
                'scenario_code' => $filters['scenario_code'] ?? '',
                'location_id' => $locationId,
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'locations' => $locations->map(fn ($location) => $location->only(['id', 'name']))->values(),
            'document_types' => $types->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->business_display_name ?: ($type->industry_display_name ?: $type->name),
                'category' => $type->category,
                'scenario_code' => $type->scenario_code,
            ])->values(),
            'experience' => $this->industryExperience($business, $user, $permissions),
            'permissions' => $permissions,
            'summary' => $this->summary($businessId, $user, $permissions, $locationId, $types),
            'records' => $this->records($view, $business, $user, $permissions, $filters, $types),
            'links' => $this->links($permissions),
            'legacy_url' => $this->legacyUrl($view),
        ];
    }

    private function records(
        string $view,
        Business $business,
        User $user,
        array $permissions,
        array $filters,
        Collection $types
    ): array {
        if ($view === 'overview') {
            if ($permissions['invoice_view']) {
                return $this->transactions('invoices', (int) $business->id, $user, $permissions, array_merge($filters, ['per_page' => 10]), optional($business->industry)->code);
            }

            return $this->emptyPage();
        }
        if (in_array($view, ['invoices', 'orders', 'quotations', 'drafts', 'returns', 'fulfilment'], true)) {
            return $this->transactions($view, (int) $business->id, $user, $permissions, $filters, optional($business->industry)->code);
        }
        if ($view === 'documents') {
            return $this->documents($business, $user, $permissions, $filters, $types, false);
        }
        if (in_array($view, ['payments', 'receipts'], true)) {
            return $this->receipts($business, $user, $permissions, $filters, $types);
        }

        return $this->customers((int) $business->id, $user, $permissions, $filters);
    }

    private function transactions(
        string $view,
        int $businessId,
        User $user,
        array $permissions,
        array $filters,
        ?string $industryCode = null
    ): array {
        $query = Transaction::query()
            ->where('transactions.business_id', $businessId)
            ->with(['contact:id,name,supplier_business_name,mobile,email', 'location:id,name', 'sales_person:id,surname,first_name,last_name'])
            ->select('transactions.*')
            ->selectRaw('(SELECT COALESCE(SUM(CASE WHEN TP.is_return = 1 THEN -TP.amount ELSE TP.amount END), 0) FROM transaction_payments AS TP WHERE TP.transaction_id = transactions.id AND (TP.payment_purpose IS NULL OR TP.payment_purpose <> ?)) AS total_paid', ['security_deposit']);
        $this->scopeSales($query, $user, $businessId, $permissions, $view);

        if ($view === 'orders') {
            $query->where('transactions.type', 'sales_order')
                ->whereIn('transactions.status', ['ordered', 'partial', 'completed']);
        } elseif ($view === 'quotations') {
            $query->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->where(function ($nested) {
                    $nested->where('transactions.is_quotation', true)
                        ->orWhereIn('transactions.sub_status', ['quotation', 'proforma']);
                });
        } elseif ($view === 'drafts') {
            $query->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->where(function ($nested) {
                    $nested->whereNull('transactions.is_quotation')->orWhere('transactions.is_quotation', false);
                })
                ->where(function ($nested) {
                    $nested->whereNull('transactions.sub_status')
                        ->orWhereNotIn('transactions.sub_status', ['quotation', 'proforma']);
                });
        } elseif ($view === 'returns') {
            $query->where('transactions.type', 'sell_return');
        } elseif ($view === 'fulfilment') {
            $query->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->whereNotNull('transactions.shipping_status');
        } else {
            $query->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->where(function ($nested) {
                    $nested->whereNull('transactions.sub_status')
                        ->orWhereNotIn('transactions.sub_status', ['quotation', 'proforma']);
                });
        }
        $this->applyTransactionFilters($query, $filters, $view);
        $paginator = $query->latest('transactions.transaction_date')->latest('transactions.id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return $this->page($paginator, fn (Transaction $transaction) => $this->transactionRow($transaction, $permissions, $view, $industryCode));
    }

    private function transactionRow(Transaction $transaction, array $permissions, string $view, ?string $industryCode = null): array
    {
        $paid = round((float) ($transaction->total_paid ?? 0), 4);
        $total = round((float) $transaction->final_total, 4);
        $quotation = (bool) $transaction->is_quotation || in_array($transaction->sub_status, ['quotation', 'proforma'], true);
        $order = $transaction->type === 'sales_order';
        $return = $transaction->type === 'sell_return';
        $draft = $view === 'drafts';
        $canEdit = $permissions['admin']
            || ($quotation && $permissions['quotation_update'])
            || (! $quotation && ($permissions['sell_update'] || $permissions['direct_sell_update']));
        $editController = (int) $transaction->is_direct_sale === 1
            ? [\App\Http\Controllers\SellController::class, 'edit']
            : [\App\Http\Controllers\SellPosController::class, 'edit'];

        return [
            'id' => $transaction->id,
            'kind' => $order ? 'Sales order' : ($return ? 'Sales return' : ($draft ? 'Draft invoice' : ($quotation ? ($transaction->sub_status === 'proforma' ? 'Proforma invoice' : 'Quotation') : 'Sales invoice'))),
            'number' => $transaction->invoice_no ?: 'Sale #'.$transaction->id,
            'date' => $transaction->transaction_date ? Carbon::parse($transaction->transaction_date)->toAtomString() : null,
            'customer' => optional($transaction->contact)->supplier_business_name ?: (optional($transaction->contact)->name ?: 'Walk-in customer'),
            'customer_mobile' => optional($transaction->contact)->mobile,
            'location' => optional($transaction->location)->name ?: 'Not assigned',
            'owner' => trim(collect([optional($transaction->sales_person)->surname, optional($transaction->sales_person)->first_name, optional($transaction->sales_person)->last_name])->filter()->implode(' ')),
            'status' => $view === 'fulfilment' ? ($transaction->shipping_status ?: 'not_started') : ($quotation ? ($transaction->sub_status ?: 'quotation') : $transaction->status),
            'payment_status' => ($quotation || $order || $draft || $return || $view === 'fulfilment') ? null : Transaction::getPaymentStatus($transaction),
            'total' => $total,
            'paid' => $paid,
            'due' => max(0, round($total - $paid, 4)),
            'actions' => [
                'view' => $return && $transaction->return_parent_id
                    ? action([\App\Http\Controllers\SellController::class, 'show'], [$transaction->return_parent_id])
                    : action([\App\Http\Controllers\SellController::class, 'show'], [$transaction->id]),
                'preview' => (! $order && ! $return && ! $draft) ? route('sell.document.preview', $transaction->id) : null,
                'print' => (! $order && ! $return && ! $draft && $permissions['print']) ? route('sell.document.print', $transaction->id) : null,
                'download' => (! $order && ! $return && ! $draft && $permissions['print'] && config('constants.enable_download_pdf'))
                    ? ($quotation
                        ? route('quotation.downloadPdf', ['id' => $transaction->id, 'sub_status' => $transaction->sub_status === 'proforma' ? 'proforma' : ''])
                        : route('sell.downloadPdf', $transaction->id))
                    : null,
                'share' => (! $order && ! $return && ! $draft && $permissions['sell_share']) ? action([\App\Http\Controllers\SellPosController::class, 'showInvoiceUrl'], [$transaction->id]) : null,
                'edit' => ($canEdit && ! $return) ? action($editController, [$transaction->id]) : null,
                'smart_document' => (! $order && ! $return && $permissions['document_create'])
                    ? route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id])
                    : null,
                'a4_invoice' => (! $order && ! $return && ! $quotation && ! $draft && $permissions['document_create']
                    && in_array($industryCode, ['restaurant_food_service', 'hotel_with_restaurant'], true))
                    ? route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id, 'scenario' => 'sale', 'type' => 'standard_invoice'])
                    : null,
                'pos_receipt' => (! $order && ! $return && ! $quotation && ! $draft && $permissions['document_create']
                    && $paid + 0.0001 >= $total
                    && in_array($industryCode, ['restaurant_food_service', 'hotel_with_restaurant'], true))
                    ? route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id, 'scenario' => 'payment', 'type' => 'pos_receipt'])
                    : null,
            ],
        ];
    }

    private function documents(
        Business $business,
        User $user,
        array $permissions,
        array $filters,
        Collection $types,
        bool $receiptsOnly
    ): array {
        if (! $permissions['document_view'] || ! Schema::hasTable('business_documents')) {
            return $this->emptyPage();
        }
        $allowedIds = $types->pluck('id');
        $query = BusinessDocument::forBusiness($business->id)
            ->with(['type', 'contact:id,name,supplier_business_name,mobile,email', 'location:id,name'])
            ->whereIn('document_type_id', $allowedIds)
            ->select('business_documents.*');
        $this->scopeDocumentLocations($query, $user, (int) $business->id);
        app(PropertyAccessService::class)->scopeBusinessDocuments($query, (int) $business->id, $user);
        if ($receiptsOnly) {
            $query->whereHas('type', fn ($type) => $type->where('code', 'like', '%receipt%'));
        }
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));
        $query->when($filters['document_type_id'] ?? null, fn ($builder, $id) => $builder->where('document_type_id', $id));
        $query->when($filters['scenario_code'] ?? null, fn ($builder, $scenario) => $builder->where('scenario_code', $scenario));
        $query->when($filters['location_id'] ?? null, fn ($builder, $id) => $builder->where('location_id', $id));
        $query->when($filters['start'] ?? null, fn ($builder, $date) => $builder->whereDate('issue_date', '>=', $date));
        $query->when($filters['end'] ?? null, fn ($builder, $date) => $builder->whereDate('issue_date', '<=', $date));
        $query->when($filters['q'] ?? null, function ($builder, $term) {
            $builder->where(function ($nested) use ($term) {
                $nested->where('document_number', 'like', '%'.$term.'%')
                    ->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhere('subject', 'like', '%'.$term.'%')
                    ->orWhereHas('contact', fn ($contact) => $contact->where('name', 'like', '%'.$term.'%'));
            });
        });
        $paginator = $query->latest('issue_date')->latest('id')->paginate((int) ($filters['per_page'] ?? 25));

        return $this->page($paginator, function (BusinessDocument $document) use ($permissions) {
            return [
                'id' => $document->id,
                'kind' => data_get($document->template_snapshot, 'display_name', optional($document->type)->name ?: 'Document'),
                'number' => $document->document_number,
                'title' => $document->title,
                'scenario' => $document->scenario_code,
                'date' => optional($document->issue_date)->toAtomString(),
                'customer' => optional($document->contact)->supplier_business_name
                    ?: (optional($document->contact)->name ?: data_get($document->party_snapshot, 'name', 'Walk-in / not assigned')),
                'location' => optional($document->location)->name ?: 'Not assigned',
                'status' => $document->status,
                'total' => round((float) $document->total_amount, 4),
                'paid' => round((float) $document->amount_paid, 4),
                'due' => round((float) $document->balance_due, 4),
                'actions' => [
                    'view' => route('smart-documents.show', $document),
                    'preview' => route('smart-documents.preview', $document),
                    'print' => route('smart-documents.print', $document),
                    'download' => route('smart-documents.download', $document),
                    'share' => $permissions['document_share'] && $document->status !== 'draft' ? route('smart-documents.show', $document) : null,
                    'edit' => $document->status === 'draft' && $permissions['document_update'] ? route('smart-documents.edit', $document) : null,
                ],
            ];
        });
    }

    private function receipts(
        Business $business,
        User $user,
        array $permissions,
        array $filters,
        Collection $types
    ): array {
        $documentReceipts = $this->documents($business, $user, $permissions, $filters, $types, true);
        $payments = collect();
        if ($permissions['invoice_view'] && Schema::hasTable('transaction_payments')) {
            $query = TransactionPayment::query()
                ->join('transactions', 'transactions.id', '=', 'transaction_payments.transaction_id')
                ->leftJoin('contacts', 'contacts.id', '=', 'transactions.contact_id')
                ->leftJoin('business_locations', 'business_locations.id', '=', 'transactions.location_id')
                ->where('transactions.business_id', $business->id)
                ->where('transactions.type', 'sell')
                ->where('transaction_payments.is_return', false)
                ->where(function ($purpose) {
                    $purpose->whereNull('transaction_payments.payment_purpose')
                        ->orWhere('transaction_payments.payment_purpose', '!=', 'security_deposit');
                })
                ->select([
                    'transaction_payments.id', 'transaction_payments.transaction_id', 'transaction_payments.amount',
                    'transaction_payments.method', 'transaction_payments.payment_ref_no', 'transaction_payments.paid_on',
                    'transactions.invoice_no', 'transactions.created_by', 'transactions.commission_agent',
                    'transactions.location_id', 'contacts.name as customer_name', 'contacts.supplier_business_name',
                    'business_locations.name as location_name',
                ]);
            $this->scopeSales($query, $user, (int) $business->id, $permissions, 'invoices', 'transactions');
            $this->scopeLocation($query, $user, (int) $business->id, 'transactions.location_id');
            $query->when($filters['location_id'] ?? null, fn ($builder, $id) => $builder->where('transactions.location_id', $id));
            $query->when($filters['start'] ?? null, fn ($builder, $date) => $builder->whereDate('transaction_payments.paid_on', '>=', $date));
            $query->when($filters['end'] ?? null, fn ($builder, $date) => $builder->whereDate('transaction_payments.paid_on', '<=', $date));
            $query->when($filters['q'] ?? null, function ($builder, $term) {
                $builder->where(function ($nested) use ($term) {
                    $nested->where('transaction_payments.payment_ref_no', 'like', '%'.$term.'%')
                        ->orWhere('transactions.invoice_no', 'like', '%'.$term.'%')
                        ->orWhere('contacts.name', 'like', '%'.$term.'%');
                });
            });
            $payments = $query->latest('transaction_payments.paid_on')->latest('transaction_payments.id')
                ->limit(12)->get()->map(fn ($payment) => [
                    'id' => $payment->id,
                    'kind' => 'Payment',
                    'number' => $payment->payment_ref_no ?: 'Payment #'.$payment->id,
                    'invoice_number' => $payment->invoice_no ?: 'Sale #'.$payment->transaction_id,
                    'date' => $payment->paid_on ? Carbon::parse($payment->paid_on)->toAtomString() : null,
                    'customer' => $payment->supplier_business_name ?: ($payment->customer_name ?: 'Walk-in customer'),
                    'location' => $payment->location_name ?: 'Not assigned',
                    'method' => $payment->method ?: 'other',
                    'amount' => round((float) $payment->amount, 4),
                    'actions' => [
                        'view' => action([\App\Http\Controllers\TransactionPaymentController::class, 'viewPayment'], [$payment->id]),
                        'invoice' => route('sell.document.preview', $payment->transaction_id),
                    ],
                ]);
        }

        return [
            'data' => $documentReceipts['data'],
            'meta' => $documentReceipts['meta'],
            'payments' => $payments->values(),
        ];
    }

    private function customers(int $businessId, User $user, array $permissions, array $filters): array
    {
        $query = Contact::where('contacts.business_id', $businessId)
            ->whereIn('contacts.type', ['customer', 'both'])
            ->select([
                'contacts.id', 'contacts.contact_id', 'contacts.name', 'contacts.supplier_business_name',
                'contacts.email', 'contacts.mobile', 'contacts.city', 'contacts.state', 'contacts.country',
                'contacts.contact_status', 'contacts.credit_limit', 'contacts.balance', 'contacts.created_at', 'contacts.created_by',
            ]);
        if (! $permissions['admin'] && ! $permissions['customer_view_all']) {
            $query->leftJoin('user_contact_access as commercial_uca', 'commercial_uca.contact_id', '=', 'contacts.id')
                ->where(function ($access) use ($user) {
                    $access->where('contacts.created_by', $user->id)
                        ->orWhere('commercial_uca.user_id', $user->id);
                })->distinct();
        }
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('contacts.contact_status', $status));
        $query->when($filters['q'] ?? null, function ($builder, $term) {
            $builder->where(function ($nested) use ($term) {
                $nested->where('contacts.name', 'like', '%'.$term.'%')
                    ->orWhere('contacts.supplier_business_name', 'like', '%'.$term.'%')
                    ->orWhere('contacts.contact_id', 'like', '%'.$term.'%')
                    ->orWhere('contacts.email', 'like', '%'.$term.'%')
                    ->orWhere('contacts.mobile', 'like', '%'.$term.'%');
            });
        });
        $paginator = $query->orderBy('contacts.name')->paginate((int) ($filters['per_page'] ?? 25));

        return $this->page($paginator, fn (Contact $contact) => [
            'id' => $contact->id,
            'kind' => 'Customer',
            'number' => $contact->contact_id ?: 'Customer #'.$contact->id,
            'name' => $contact->supplier_business_name ?: $contact->name,
            'person_name' => $contact->supplier_business_name ? $contact->name : null,
            'email' => $contact->email,
            'mobile' => $contact->mobile,
            'location' => collect([$contact->city, $contact->state, $contact->country])->filter()->implode(', '),
            'status' => $contact->contact_status ?: 'active',
            'credit_limit' => $contact->credit_limit === null ? null : round((float) $contact->credit_limit, 4),
            'advance_balance' => round((float) $contact->balance, 4),
            'actions' => [
                'view' => action([\App\Http\Controllers\ContactController::class, 'show'], [$contact->id]),
                'ledger' => url('/contacts/ledger?contact_id='.$contact->id),
            ],
        ]);
    }

    private function summary(int $businessId, User $user, array $permissions, ?int $locationId, Collection $types): array
    {
        $summary = [
            'invoice_count' => null,
            'invoice_total' => null,
            'amount_due' => null,
            'quotation_count' => null,
            'order_count' => null,
            'draft_count' => null,
            'return_count' => null,
            'fulfilment_count' => null,
            'document_action_count' => null,
            'customer_count' => null,
        ];
        if ($permissions['invoice_view']) {
            $sales = Transaction::where('transactions.business_id', $businessId)->where('transactions.type', 'sell');
            $this->scopeSales($sales, $user, $businessId, $permissions, 'invoices');
            $this->scopeLocation($sales, $user, $businessId, 'transactions.location_id');
            $sales->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['invoice_count'] = (clone $sales)->where('transactions.status', 'final')->count();
            $summary['invoice_total'] = round((float) (clone $sales)->where('transactions.status', 'final')->sum('transactions.final_total'), 4);
            $dueRow = (clone $sales)->where('transactions.status', 'final')
                ->whereIn('transactions.payment_status', ['due', 'partial'])
                ->selectRaw('COALESCE(SUM(transactions.final_total - (SELECT COALESCE(SUM(CASE WHEN summary_tp.is_return = 1 THEN -summary_tp.amount ELSE summary_tp.amount END), 0) FROM transaction_payments AS summary_tp WHERE summary_tp.transaction_id = transactions.id AND (summary_tp.payment_purpose IS NULL OR summary_tp.payment_purpose <> ?))), 0) AS amount_due', ['security_deposit'])
                ->first();
            $summary['amount_due'] = round(max(0, (float) optional($dueRow)->amount_due), 4);
        }
        if ($permissions['quotation_view']) {
            $quotations = Transaction::where('transactions.business_id', $businessId)->where('transactions.type', 'sell');
            $this->scopeSales($quotations, $user, $businessId, $permissions, 'quotations');
            $this->scopeLocation($quotations, $user, $businessId, 'transactions.location_id');
            $quotations->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['quotation_count'] = $quotations->where('transactions.status', 'draft')
                ->where(function ($query) {
                    $query->where('transactions.is_quotation', true)->orWhereIn('transactions.sub_status', ['quotation', 'proforma']);
                })->count();
        }
        if ($permissions['order_view']) {
            $orders = Transaction::where('transactions.business_id', $businessId)
                ->where('transactions.type', 'sales_order')
                ->whereIn('transactions.status', ['ordered', 'partial']);
            $this->scopeSales($orders, $user, $businessId, $permissions, 'orders');
            $this->scopeLocation($orders, $user, $businessId, 'transactions.location_id');
            $orders->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['order_count'] = $orders->count();
        }
        if ($permissions['draft_view']) {
            $drafts = Transaction::where('transactions.business_id', $businessId)
                ->where('transactions.type', 'sell')->where('transactions.status', 'draft')
                ->where(function ($query) {
                    $query->whereNull('transactions.is_quotation')->orWhere('transactions.is_quotation', false);
                })->where(function ($query) {
                    $query->whereNull('transactions.sub_status')
                        ->orWhereNotIn('transactions.sub_status', ['quotation', 'proforma']);
                });
            $this->scopeSales($drafts, $user, $businessId, $permissions, 'drafts');
            $this->scopeLocation($drafts, $user, $businessId, 'transactions.location_id');
            $drafts->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['draft_count'] = $drafts->count();
        }
        if ($permissions['return_view']) {
            $returns = Transaction::where('transactions.business_id', $businessId)->where('transactions.type', 'sell_return');
            $this->scopeSales($returns, $user, $businessId, $permissions, 'returns');
            $this->scopeLocation($returns, $user, $businessId, 'transactions.location_id');
            $returns->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['return_count'] = $returns->count();
        }
        if ($permissions['fulfilment_view']) {
            $fulfilment = Transaction::where('transactions.business_id', $businessId)
                ->where('transactions.type', 'sell')->where('transactions.status', 'final')
                ->whereNotNull('transactions.shipping_status')->where('transactions.shipping_status', '!=', 'delivered');
            $this->scopeSales($fulfilment, $user, $businessId, $permissions, 'fulfilment');
            $this->scopeLocation($fulfilment, $user, $businessId, 'transactions.location_id');
            $fulfilment->when($locationId, fn ($query, $id) => $query->where('transactions.location_id', $id));
            $summary['fulfilment_count'] = $fulfilment->count();
        }
        if ($permissions['document_view'] && Schema::hasTable('business_documents')) {
            $documents = BusinessDocument::forBusiness($businessId)
                ->whereIn('document_type_id', $types->pluck('id'))
                ->whereIn('status', ['draft', 'issued', 'sent', 'accepted']);
            $this->scopeDocumentLocations($documents, $user, $businessId);
            app(PropertyAccessService::class)->scopeBusinessDocuments($documents, $businessId, $user);
            $documents->when($locationId, fn ($query, $id) => $query->where('location_id', $id));
            $summary['document_action_count'] = $documents->count();
        }
        if ($permissions['customer_view']) {
            $customers = Contact::where('contacts.business_id', $businessId)->whereIn('contacts.type', ['customer', 'both']);
            if (! $permissions['admin'] && ! $permissions['customer_view_all']) {
                $customers->leftJoin('user_contact_access as summary_uca', 'summary_uca.contact_id', '=', 'contacts.id')
                    ->where(fn ($query) => $query->where('contacts.created_by', $user->id)->orWhere('summary_uca.user_id', $user->id))
                    ->distinct('contacts.id');
            }
            $summary['customer_count'] = $customers->count('contacts.id');
        }

        return $summary;
    }

    private function permissions(User $user, int $businessId): array
    {
        $admin = $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
        $can = fn ($ability) => $admin || $user->canForBusiness($ability, $businessId);
        $salesAll = collect(['sell.view', 'direct_sell.view', 'direct_sell.access'])->contains($can);
        $salesOwn = $can('view_own_sell_only') || $can('view_commission_agent_sell');
        $documentsEnabled = app(FeatureAccessService::class)->enabled('smart_documents', $businessId);

        return [
            'admin' => $admin,
            'invoice_view' => $salesAll || $salesOwn,
            'quotation_view' => $can('quotation.view_all') || $can('quotation.view_own') || $salesAll || $salesOwn,
            'sales_view' => $salesAll || $salesOwn || $can('quotation.view_all') || $can('quotation.view_own'),
            'sales_view_all' => $salesAll,
            'sales_view_own' => $salesOwn,
            'commission_sales' => $can('view_commission_agent_sell'),
            'quotation_view_all' => $can('quotation.view_all') || $salesAll,
            'quotation_view_own' => $can('quotation.view_own') || $salesOwn,
            'order_view' => $can('so.view_all') || $can('so.view_own') || $can('so.create'),
            'order_view_all' => $can('so.view_all'),
            'order_view_own' => $can('so.view_own') || $can('so.create'),
            'draft_view' => $can('draft.view_all') || $can('draft.view_own') || $salesAll || $salesOwn,
            'draft_view_all' => $can('draft.view_all') || $salesAll,
            'draft_view_own' => $can('draft.view_own') || $salesOwn,
            'return_view' => $can('access_sell_return') || $can('access_own_sell_return'),
            'return_view_all' => $can('access_sell_return'),
            'return_view_own' => $can('access_own_sell_return'),
            'fulfilment_view' => $can('access_shipping') || $can('access_own_shipping') || $can('access_commission_agent_shipping'),
            'fulfilment_view_all' => $can('access_shipping'),
            'fulfilment_view_own' => $can('access_own_shipping'),
            'sell_create' => $can('direct_sell.access') || $can('sell.create'),
            'direct_sell_create' => $can('direct_sell.access'),
            'quotation_create' => $can('direct_sell.access'),
            'sell_update' => $can('sell.update'),
            'direct_sell_update' => $can('direct_sell.update'),
            'quotation_update' => $can('quotation.update'),
            'print' => $can('print_invoice'),
            'sell_share' => $can('sell.document.share'),
            'customer_view' => $can('customer.view') || $can('customer.view_own'),
            'customer_view_all' => $can('customer.view'),
            'customer_create' => $can('customer.create'),
            'document_view' => $documentsEnabled && $can('smart_documents.view'),
            'document_create' => $documentsEnabled && $can('smart_documents.create'),
            'document_update' => $documentsEnabled && $can('smart_documents.update'),
            'document_share' => $documentsEnabled && $can('smart_documents.share'),
            'document_settings' => $documentsEnabled && $can('smart_documents.settings'),
            'discount_access' => $can('discount.access'),
        ];
    }

    private function industryExperience(Business $business, User $user, array $permissions): array
    {
        $industryCode = optional($business->industry)->code;
        $profiles = [
            'restaurant_food_service' => [
                'eyebrow' => 'Restaurant sales and guest orders',
                'title' => 'Orders, bills and customer payments',
                'description' => 'Manage counter, table, takeaway, delivery and catering sales without mixing kitchen preparation with financial records.',
                'party_label' => 'Guest / customer',
                'invoice_label' => 'Bill / sales invoice',
                'shortcuts' => [
                    ['label' => 'Restaurant operations', 'url' => '/restaurant-operations', 'icon' => 'fa-cutlery', 'feature' => 'restaurant_operations', 'permissions' => ['restaurant.dashboard.view', 'restaurant.orders.view']],
                    ['label' => 'Kitchen board', 'url' => '/restaurant-operations/kitchen-board', 'icon' => 'fa-fire', 'feature' => 'restaurant_operations', 'permissions' => ['restaurant.kitchen.view', 'restaurant.kitchen.manage']],
                ],
            ],
            'hotel_lodge_guesthouse' => [
                'eyebrow' => 'Hospitality billing and guest revenue',
                'title' => 'Guest folios, invoices and payments',
                'description' => 'Keep reservations and room operations in Hotel Management while controlling guest folios, invoices, receipts and event documents here.',
                'party_label' => 'Guest / account',
                'invoice_label' => 'Guest invoice',
                'shortcuts' => [
                    ['label' => 'Front desk', 'url' => '/hms/front-desk', 'icon' => 'fa-bed', 'feature' => 'hms', 'permissions' => ['hms.front_desk', 'hms.manage_front_desk']],
                    ['label' => 'Guest folios', 'url' => '/hms/folios', 'icon' => 'fa-list-alt', 'feature' => 'hms', 'permissions' => ['hms.view_bookings', 'hms.manage_front_desk']],
                    ['label' => 'Reservations', 'url' => '/hms/bookings', 'icon' => 'fa-calendar-check-o', 'feature' => 'hms', 'permissions' => ['hms.view_bookings', 'hms.add_booking', 'hms.edit_booking']],
                ],
            ],
            'hotel_with_restaurant' => [
                'eyebrow' => 'Hospitality, restaurant and event revenue',
                'title' => 'Guest folios, outlet sales and payments',
                'description' => 'A combined revenue view with separate hotel, restaurant and kitchen operational workspaces for proper accountability.',
                'party_label' => 'Guest / customer',
                'invoice_label' => 'Guest / sales invoice',
                'shortcuts' => [
                    ['label' => 'Front desk', 'url' => '/hms/front-desk', 'icon' => 'fa-bed', 'feature' => 'hms', 'permissions' => ['hms.front_desk', 'hms.manage_front_desk']],
                    ['label' => 'Guest folios', 'url' => '/hms/folios', 'icon' => 'fa-list-alt', 'feature' => 'hms', 'permissions' => ['hms.view_bookings', 'hms.manage_front_desk']],
                    ['label' => 'Restaurant operations', 'url' => '/restaurant-operations', 'icon' => 'fa-cutlery', 'feature' => 'restaurant_operations', 'permissions' => ['restaurant.dashboard.view', 'restaurant.orders.view']],
                ],
            ],
            'property_management_rentals' => [
                'eyebrow' => 'Property revenue and tenant billing',
                'title' => 'Rental invoices, receipts and tenant accounts',
                'description' => 'Control billable property transactions here while leases, units, rent schedules and refundable security deposits stay in Property Management.',
                'party_label' => 'Tenant / client',
                'invoice_label' => 'Rental invoice',
                'shortcuts' => [
                    ['label' => 'Property portfolio', 'url' => '/property-management', 'icon' => 'fa-building-o', 'feature' => 'property_management', 'permissions' => ['property.view']],
                    ['label' => 'Leases and rent', 'url' => '/property-management/rents', 'icon' => 'fa-key', 'feature' => 'property_management', 'permissions' => ['property.rent.manage']],
                    ['label' => 'Maintenance', 'url' => '/property-management/maintenance', 'icon' => 'fa-wrench', 'feature' => 'property_management', 'permissions' => ['property.maintenance.manage']],
                ],
            ],
            'professional_services' => [
                'eyebrow' => 'Client and project billing',
                'title' => 'Proposals, invoices and receivables',
                'description' => 'Manage client quotations, invoices, recurring payments and documents while delivery work remains in Projects and CRM.',
                'party_label' => 'Client',
                'invoice_label' => 'Client invoice',
                'shortcuts' => [
                    ['label' => 'CRM pipeline', 'url' => '/crm/dashboard', 'icon' => 'fa-bullseye', 'feature' => 'crm', 'permissions' => ['crm.workspace.view', 'crm.access_all_leads', 'crm.access_own_leads']],
                    ['label' => 'Projects', 'url' => '/project/project', 'icon' => 'fa-briefcase', 'feature' => 'projects', 'permissions' => ['project.view', 'project.create']],
                ],
            ],
        ];

        $profile = $profiles[$industryCode] ?? [
            'eyebrow' => 'Sales and distribution',
            'title' => 'Quotations, orders, invoices and payments',
            'description' => 'A controlled order-to-cash workspace for retail, wholesale, distribution and service billing.',
            'party_label' => 'Customer',
            'invoice_label' => 'Sales invoice',
            'shortcuts' => [],
        ];
        $features = app(FeatureAccessService::class);
        $profile['shortcuts'] = collect($profile['shortcuts'])->filter(function (array $shortcut) use ($business, $user, $permissions, $features) {
            if (! $features->enabled($shortcut['feature'], (int) $business->id)) {
                return false;
            }
            if ($permissions['admin']) {
                return true;
            }

            return collect($shortcut['permissions'])->contains(fn ($ability) => $user->canForBusiness($ability, (int) $business->id) || $user->can($ability));
        })->map(fn (array $shortcut) => collect($shortcut)->except(['feature', 'permissions'])->all())->values()->all();

        return $profile;
    }

    private function scopeSales($query, User $user, int $businessId, array $permissions, string $view, string $prefix = 'transactions'): void
    {
        if ($permissions['admin']) {
            return;
        }
        $all = match ($view) {
            'quotations' => $permissions['quotation_view_all'],
            'orders' => $permissions['order_view_all'],
            'drafts' => $permissions['draft_view_all'],
            'returns' => $permissions['return_view_all'],
            'fulfilment' => $permissions['fulfilment_view_all'],
            default => $permissions['sales_view_all'],
        };
        if ($all) {
            return;
        }
        $ownAllowed = match ($view) {
            'quotations' => $permissions['quotation_view_own'],
            'orders' => $permissions['order_view_own'],
            'drafts' => $permissions['draft_view_own'],
            'returns' => $permissions['return_view_own'],
            'fulfilment' => $permissions['fulfilment_view_own'],
            default => $permissions['sales_view_own'],
        };
        $query->where(function ($access) use ($user, $permissions, $ownAllowed, $prefix) {
            if ($ownAllowed) {
                $access->where($prefix.'.created_by', $user->id);
            } else {
                $access->whereRaw('1 = 0');
            }
            if ($permissions['commission_sales']) {
                $access->orWhere($prefix.'.commission_agent', $user->id);
            }
        });
    }

    private function applyTransactionFilters(Builder $query, array $filters, string $view): void
    {
        $this->scopeLocation($query, auth()->user(), (int) session('user.business_id'), 'transactions.location_id');
        $query->when($filters['location_id'] ?? null, fn ($builder, $id) => $builder->where('transactions.location_id', $id));
        $query->when($filters['start'] ?? null, fn ($builder, $date) => $builder->whereDate('transactions.transaction_date', '>=', $date));
        $query->when($filters['end'] ?? null, fn ($builder, $date) => $builder->whereDate('transactions.transaction_date', '<=', $date));
        $query->when($filters['status'] ?? null, function ($builder, $status) use ($view) {
            if (in_array($status, ['paid', 'due', 'partial'], true)) {
                $builder->where('transactions.payment_status', $status);
            } elseif (in_array($status, ['quotation', 'proforma'], true)) {
                $builder->where('transactions.sub_status', $status);
            } elseif ($view === 'fulfilment') {
                $builder->where('transactions.shipping_status', $status);
            } elseif (in_array($status, ['ordered', 'completed', 'final', 'draft'], true)) {
                $builder->where('transactions.status', $status);
            }
        });
        $query->when($filters['q'] ?? null, function ($builder, $term) {
            $builder->where(function ($nested) use ($term) {
                $nested->where('transactions.invoice_no', 'like', '%'.$term.'%')
                    ->orWhereHas('contact', function ($contact) use ($term) {
                        $contact->where('name', 'like', '%'.$term.'%')
                            ->orWhere('supplier_business_name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.$term.'%');
                    });
            });
        });
    }

    private function scopeLocation($query, User $user, int $businessId, string $column): void
    {
        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn($column, array_map('intval', (array) $permitted));
        }
    }

    private function scopeDocumentLocations($query, User $user, int $businessId): void
    {
        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->where(function ($location) use ($permitted) {
                $location->whereIn('location_id', array_map('intval', (array) $permitted))->orWhereNull('location_id');
            });
        }
    }

    private function locations(User $user, int $businessId): Collection
    {
        $query = BusinessLocation::where('business_id', $businessId)->where('is_active', true)->orderBy('name');
        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('id', array_map('intval', (array) $permitted));
        }

        return $query->get(['id', 'name']);
    }

    private function documentTypes(Business $business, User $user, array $permissions): Collection
    {
        if (! $permissions['document_view'] || ! Schema::hasTable('document_types')) {
            return collect();
        }
        $catalog = app(IndustryDocumentCatalogService::class);

        return app(BusinessDocumentAccessService::class)
            ->permittedTypes($business, $user, $catalog->profileTypes($business));
    }

    private function links(array $permissions): array
    {
        return [
            'create_invoice' => $permissions['sell_create']
                ? action([$permissions['direct_sell_create'] ? \App\Http\Controllers\SellController::class : \App\Http\Controllers\SellPosController::class, 'create'])
                : null,
            'create_quotation' => $permissions['quotation_create'] ? action([\App\Http\Controllers\SellController::class, 'create'], ['status' => 'quotation']) : null,
            'create_draft' => $permissions['sell_create'] ? action([\App\Http\Controllers\SellController::class, 'create'], ['status' => 'draft']) : null,
            'pos' => $permissions['sell_create'] ? action([\App\Http\Controllers\SellPosController::class, 'create']) : null,
            'discounts' => $permissions['discount_access'] ? action([\App\Http\Controllers\DiscountController::class, 'index']) : null,
            'create_document' => $permissions['document_create'] ? route('smart-documents.create') : null,
            'document_settings' => $permissions['document_settings'] ? route('smart-documents.settings') : null,
            'create_customer' => $permissions['customer_create'] ? route('commercial.customers.store') : null,
            'import_customers' => $permissions['customer_create'] ? route('contacts.import') : null,
            'import_sales' => $permissions['sell_create'] ? url('/import-sales') : null,
        ];
    }

    private function viewAllowed(string $view, array $permissions): bool
    {
        return match ($view) {
            'overview' => $permissions['invoice_view'] || $permissions['quotation_view'] || $permissions['order_view'] || $permissions['draft_view'] || $permissions['return_view'] || $permissions['fulfilment_view'],
            'invoices' => $permissions['invoice_view'],
            'orders' => $permissions['order_view'],
            'quotations' => $permissions['quotation_view'],
            'drafts' => $permissions['draft_view'],
            'returns' => $permissions['return_view'],
            'fulfilment' => $permissions['fulfilment_view'],
            'payments', 'receipts' => $permissions['invoice_view'] || $permissions['document_view'],
            'documents' => $permissions['document_view'],
            'customers' => $permissions['customer_view'],
            default => false,
        };
    }

    private function legacyUrl(string $view): string
    {
        return match ($view) {
            'overview' => url('/sells?legacy=1'),
            'orders' => url('/sales-order?legacy=1'),
            'quotations' => url('/sells/quotations?legacy=1'),
            'drafts' => url('/sells/drafts?legacy=1'),
            'returns' => url('/sell-return?legacy=1'),
            'fulfilment' => url('/shipments?legacy=1'),
            'documents', 'payments', 'receipts' => url('/smart-documents?legacy=1'),
            'customers' => url('/contacts?type=customer&legacy=1'),
            default => url('/sells?legacy=1'),
        };
    }

    private function page(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    private function emptyPage(): array
    {
        return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0, 'from' => null, 'to' => null]];
    }
}
