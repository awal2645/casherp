<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Http\Requests\StoreProcurementQuoteRequest;
use App\ProcurementDocument;
use App\ProcurementSupplierQuote;
use App\TaxRate;
use App\Transaction;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\Services\ProcurementWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProcurementWorkflowController extends Controller
{
    public function __construct(
        protected ProcurementWorkflowService $workflow,
        protected BusinessUtil $businessUtil,
        protected TransactionUtil $transactionUtil
    ) {
    }

    public function show(Request $request, int $transaction)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $transaction = Transaction::where('business_id', $businessId)
            ->whereIn('type', ['purchase_requisition', 'purchase_order'])
            ->with(['purchase_lines.product.unit', 'purchase_lines.variations.product_variation', 'contact', 'location'])
            ->findOrFail($transaction);
        $this->authorizeView($request, $transaction);

        $document = ProcurementDocument::where('business_id', $businessId)
            ->where('transaction_id', $transaction->id)
            ->with([
                'requester', 'department', 'approvals.actor', 'events.user',
                'quotes.supplier', 'quotes.currency', 'selectedQuote.supplier',
                'selectedQuote.currency', 'parentTransaction', 'autoExpense',
            ])->first();
        abort_if(! $document, 404, 'This legacy transaction does not use the procurement approval workflow.');

        $suppliers = Contact::suppliersDropdown($businessId, false);
        $currencies = $this->businessUtil->allCurrencies();
        $taxes = TaxRate::where('business_id', $businessId)->where('is_tax_group', false)->pluck('name', 'id');
        $projectName = null;
        if ($document->project_id && Schema::hasTable('pjt_projects')) {
            $projectName = DB::table('pjt_projects')
                ->where('business_id', $businessId)
                ->where('id', $document->project_id)
                ->value('name');
        }

        return view('procurement.workflow', [
            'transaction' => $transaction,
            'document' => $document,
            'suppliers' => $suppliers,
            'currencies' => $currencies,
            'taxes' => $taxes,
            'projectName' => $projectName,
            'canAct' => $this->workflow->canAct($document, $request->user()),
            'canManageQuotes' => $request->user()->can('procurement.quote.manage'),
        ]);
    }

    public function storeQuote(StoreProcurementQuoteRequest $request, int $transaction): RedirectResponse
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $document = ProcurementDocument::where('business_id', $businessId)
            ->where('transaction_id', $transaction)
            ->where('document_type', 'requisition')
            ->with('transaction.purchase_lines')
            ->firstOrFail();

        if (! in_array($document->approval_status, ['pending_manager', 'pending_finance', 'rejected'], true)) {
            throw ValidationException::withMessages(['quote' => 'Quotations can no longer be added to this requisition.']);
        }

        $supplier = Contact::where('business_id', $businessId)
            ->whereIn('type', ['supplier', 'both'])
            ->findOrFail($request->integer('supplier_id'));
        $tax = $request->filled('tax_id')
            ? TaxRate::where('business_id', $businessId)->where('is_tax_group', false)->findOrFail($request->integer('tax_id'))
            : null;

        $prices = collect($request->input('line_prices', []))->mapWithKeys(
            fn ($price, $lineId) => [(string) (int) $lineId => round((float) $price, 4)]
        )->all();
        $lineIds = $document->transaction->purchase_lines->pluck('id')->map(fn ($id) => (string) $id)->sort()->values();
        if ($lineIds->all() !== collect(array_keys($prices))->sort()->values()->all()) {
            throw ValidationException::withMessages(['line_prices' => 'Enter one price for every requisition line.']);
        }

        $subtotal = round($document->transaction->purchase_lines->sum(function ($line) use ($prices) {
            $quantity = (float) $line->quantity > 0
                ? (float) $line->quantity
                : (float) $line->secondary_unit_quantity;

            return $quantity * (float) $prices[(string) $line->id];
        }), 4);
        $taxAmount = $tax ? round($subtotal * (float) $tax->amount / 100, 4) : 0;
        $shippingAmount = round((float) $request->input('shipping_amount', 0), 4);
        $attachment = $this->transactionUtil->uploadFile($request, 'attachment', 'documents');

        $quote = DB::transaction(function () use ($request, $businessId, $document, $supplier, $tax, $prices, $subtotal, $taxAmount, $shippingAmount, $attachment) {
            $quote = ProcurementSupplierQuote::create([
                'business_id' => $businessId,
                'requisition_transaction_id' => $document->transaction_id,
                'supplier_id' => $supplier->id,
                'currency_id' => $request->integer('currency_id'),
                'tax_id' => $tax?->id,
                'quote_reference' => $request->input('quote_reference'),
                'exchange_rate' => $request->input('exchange_rate'),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total' => $subtotal + $taxAmount + $shippingAmount,
                'delivery_date' => $request->input('delivery_date'),
                'valid_until' => $request->input('valid_until'),
                'line_prices' => $prices,
                'notes' => $request->input('notes'),
                'attachment' => $attachment,
                'status' => 'received',
                'entered_by' => $request->user()->id,
            ]);
            $this->workflow->quoteAdded($document, $quote, $request->user());

            return $quote;
        });

        return redirect()->route('procurement.workflow.show', $transaction)->with('status', [
            'success' => 1,
            'msg' => 'Supplier quotation added. Compare the offers and select the preferred supplier.',
        ]);
    }

    public function selectQuote(Request $request, int $transaction, int $quote): RedirectResponse
    {
        $request->validate(['_token' => ['required']]);
        $businessId = (int) $request->session()->get('user.business_id');
        $document = ProcurementDocument::where('business_id', $businessId)
            ->where('transaction_id', $transaction)->firstOrFail();
        $quote = ProcurementSupplierQuote::where('business_id', $businessId)
            ->where('requisition_transaction_id', $transaction)->findOrFail($quote);
        $this->workflow->selectQuote($document, $quote, $request->user());

        return back()->with('status', ['success' => 1, 'msg' => 'Preferred supplier selected.']);
    }

    public function approve(Request $request, int $transaction): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $document = $this->documentForCurrentBusiness($request, $transaction);
        $updated = $this->workflow->approve($document, $request->user(), $data['note'] ?? null);
        $message = $updated->approval_status === 'approved'
            ? 'Document approved. The next transaction was created automatically.'
            : 'Approval recorded and sent to the next stage.';

        return back()->with('status', ['success' => 1, 'msg' => $message]);
    }

    public function reject(Request $request, int $transaction): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:3000']]);
        $document = $this->documentForCurrentBusiness($request, $transaction);
        $this->workflow->reject($document, $request->user(), $data['note']);

        return back()->with('status', ['success' => 1, 'msg' => 'Document rejected with an audit note.']);
    }

    public function resubmit(Request $request, int $transaction): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $document = $this->documentForCurrentBusiness($request, $transaction);
        $this->workflow->resubmit($document, $request->user(), $data['note'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => 'Document resubmitted for Manager approval.']);
    }

    private function documentForCurrentBusiness(Request $request, int $transaction): ProcurementDocument
    {
        return ProcurementDocument::where('business_id', (int) $request->session()->get('user.business_id'))
            ->where('transaction_id', $transaction)
            ->firstOrFail();
    }

    private function authorizeView(Request $request, Transaction $transaction): void
    {
        $user = $request->user();
        $prefix = $transaction->type === 'purchase_requisition' ? 'purchase_requisition' : 'purchase_order';
        $hasWorkflowAccess = $user->hasAnyPermission([
            'procurement.audit.view', 'procurement.quote.manage',
            'procurement.approve.manager', 'procurement.approve.finance', 'procurement.approve.admin',
        ]);
        $hasSubmitAccess = $user->can($prefix.'.create') && $user->can('procurement.submit');
        if (! $user->can($prefix.'.view_all') && ! $user->can($prefix.'.view_own') && ! $hasWorkflowAccess && ! $hasSubmitAccess) {
            abort(403, 'Unauthorized action.');
        }
        if (! $user->can($prefix.'.view_all')
            && ! $hasWorkflowAccess
            && (int) $transaction->created_by !== (int) $user->id) {
            abort(403, 'Unauthorized action.');
        }
    }
}
