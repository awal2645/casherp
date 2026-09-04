<?php

namespace App\Http\Requests;

use App\BusinessLocation;
use App\Contact;
use App\PurchaseLine;
use App\ProcurementDocument;
use App\TaxRate;
use App\Transaction;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return $this->isMethod('post')
            ? auth()->user()->can('purchase_order.create') && auth()->user()->can('procurement.submit')
            : auth()->user()->can('purchase_order.update');
    }

    protected function prepareForValidation(): void
    {
        $requisitionIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $this->input('purchase_requisition_ids', [])
        ))));

        $this->merge([
            'ref_no' => trim((string) $this->input('ref_no')) ?: null,
            'purchase_requisition_ids' => $requisitionIds,
        ]);
    }

    public function rules(): array
    {
        $businessId = (int) $this->session()->get('user.business_id');
        $referenceRule = Rule::unique('transactions', 'ref_no')
            ->where(fn ($query) => $query->where('business_id', $businessId)->where('type', 'purchase_order'));
        if ($this->route('purchase_order')) {
            $referenceRule->ignore((int) $this->route('purchase_order'));
        }

        return [
            'ref_no' => ['nullable', 'string', 'max:100', $referenceRule],
            'contact_id' => ['required', 'integer'],
            'transaction_date' => ['required', 'string', 'max:100'],
            'delivery_date' => ['nullable', 'string', 'max:100'],
            'location_id' => ['required', 'integer'],
            'exchange_rate' => ['required'],
            'total_before_tax' => ['required', 'string', 'max:50'],
            'final_total' => ['required', 'string', 'max:50'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'tax_id' => ['nullable', 'integer'],
            'pjt_project_id' => ['nullable', 'integer'],
            'document' => ['nullable', 'file', 'max:'.(config('constants.document_size_limit') / 1000)],
            'shipping_documents.*' => ['nullable', 'file', 'max:'.(config('constants.document_size_limit') / 1000)],
            'purchase_requisition_ids' => ['array', 'max:100'],
            'purchase_requisition_ids.*' => ['integer', 'distinct'],
            'purchases' => ['required', 'array', 'min:1', 'max:500'],
            'purchases.*.product_id' => ['required', 'integer'],
            'purchases.*.variation_id' => ['required', 'integer'],
            'purchases.*.quantity' => ['nullable'],
            'purchases.*.secondary_unit_quantity' => ['nullable'],
            'purchases.*.purchase_line_id' => ['nullable', 'integer'],
            'purchases.*.purchase_requisition_line_id' => ['nullable', 'integer'],
            'purchases.*.purchase_price' => ['required'],
            'purchases.*.purchase_price_inc_tax' => ['required'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $businessId = (int) $this->session()->get('user.business_id');
            $util = app(\App\Utils\Util::class);
            $routeTransactionId = $this->isMethod('post') ? null : (int) $this->route('purchase_order');

            if ($routeTransactionId) {
                $workflowDocument = ProcurementDocument::where('business_id', $businessId)
                    ->where('transaction_id', $routeTransactionId)
                    ->with('selectedQuote')
                    ->first();
                if ($workflowDocument?->parent_transaction_id
                    && $workflowDocument->selectedQuote
                    && (int) $workflowDocument->selectedQuote->supplier_id !== $this->integer('contact_id')) {
                    $validator->errors()->add('contact_id', 'The supplier on a generated purchase order must match the selected quotation.');
                }
            }

            if (! BusinessLocation::where('business_id', $businessId)->whereKey($this->integer('location_id'))->exists()) {
                $validator->errors()->add('location_id', 'Select a valid business location.');
            }

            if (! Contact::where('business_id', $businessId)
                ->whereIn('type', ['supplier', 'both'])
                ->whereKey($this->integer('contact_id'))->exists()) {
                $validator->errors()->add('contact_id', 'Select a valid supplier for this company.');
            }

            if ($this->filled('tax_id') && ! TaxRate::where('business_id', $businessId)
                ->where('is_tax_group', false)->whereKey($this->integer('tax_id'))->exists()) {
                $validator->errors()->add('tax_id', 'Select a valid tax rate for this company.');
            }

            if ($this->filled('pjt_project_id')) {
                $validProject = Schema::hasTable('pjt_projects')
                    && DB::table('pjt_projects')->where('business_id', $businessId)
                        ->where('id', $this->integer('pjt_project_id'))->exists();
                if (! $validProject) {
                    $validator->errors()->add('pjt_project_id', 'Select a valid project for this company.');
                }
            }

            try {
                $orderDate = Carbon::parse($util->uf_date($this->input('transaction_date'), true));
                if ($this->filled('delivery_date')) {
                    $deliveryDate = Carbon::parse($util->uf_date($this->input('delivery_date'), true));
                    if ($deliveryDate->lt($orderDate)) {
                        $validator->errors()->add('delivery_date', 'The delivery date cannot be earlier than the order date.');
                    }
                }
            } catch (\Throwable $exception) {
                $validator->errors()->add('transaction_date', 'Enter valid order and delivery dates.');
            }

            foreach (['exchange_rate', 'total_before_tax', 'final_total'] as $field) {
                $value = $util->num_uf($this->input($field));
                if (! is_numeric($value) || ($field === 'exchange_rate' ? (float) $value <= 0 : (float) $value < 0)) {
                    $validator->errors()->add($field, 'Enter a valid '.str_replace('_', ' ', $field).'.');
                }
            }

            $requisitionIds = (array) $this->input('purchase_requisition_ids', []);
            if ($requisitionIds) {
                $requisitions = Transaction::where('business_id', $businessId)
                    ->where('type', 'purchase_requisition')
                    ->whereIn('id', $requisitionIds)
                    ->get(['id', 'status', 'location_id']);
                if ($requisitions->count() !== count($requisitionIds)) {
                    $validator->errors()->add('purchase_requisition_ids', 'One or more purchase requisitions are invalid.');
                }

                $workflowStatuses = ProcurementDocument::where('business_id', $businessId)
                    ->whereIn('transaction_id', $requisitionIds)
                    ->pluck('approval_status', 'transaction_id');
                foreach ($requisitions as $requisition) {
                    $workflowStatus = $workflowStatuses->get($requisition->id);
                    if (($workflowStatus && $workflowStatus !== 'approved')
                        || (! $workflowStatus && ! in_array($requisition->status, ['ordered', 'partial'], true))) {
                        $validator->errors()->add('purchase_requisition_ids', 'Only approved purchase requisitions can be used in an order.');
                        break;
                    }
                    if ((int) $requisition->location_id !== $this->integer('location_id')) {
                        $validator->errors()->add('purchase_requisition_ids', 'Purchase requisitions must belong to the selected location.');
                        break;
                    }
                }
            }

            $seenVariations = [];
            foreach ((array) $this->input('purchases', []) as $index => $line) {
                $quantity = $util->num_uf($line['quantity'] ?? 0);
                $secondary = $util->num_uf($line['secondary_unit_quantity'] ?? 0);
                $purchasePrice = $util->num_uf($line['purchase_price'] ?? null);
                $purchasePriceWithTax = $util->num_uf($line['purchase_price_inc_tax'] ?? null);
                if ((float) $quantity <= 0 && (float) $secondary <= 0) {
                    $validator->errors()->add("purchases.$index.quantity", 'Each purchase order line must have a quantity greater than zero.');
                }
                if (! is_numeric($purchasePrice) || (float) $purchasePrice < 0
                    || ! is_numeric($purchasePriceWithTax) || (float) $purchasePriceWithTax < 0) {
                    $validator->errors()->add("purchases.$index.purchase_price", 'Each purchase order line must have a valid non-negative price.');
                }

                $variationId = (int) ($line['variation_id'] ?? 0);
                $productId = (int) ($line['product_id'] ?? 0);
                $validProduct = Variation::query()
                    ->join('products', 'products.id', '=', 'variations.product_id')
                    ->where('products.business_id', $businessId)
                    ->where('products.id', $productId)
                    ->where('variations.id', $variationId)
                    ->exists();
                if (! $validProduct) {
                    $validator->errors()->add("purchases.$index.product_id", 'A selected product does not belong to this company.');
                }

                $requisitionLineId = (int) ($line['purchase_requisition_line_id'] ?? 0);
                if ($requisitionLineId) {
                    $validRequisitionLine = PurchaseLine::query()
                        ->join('transactions', 'transactions.id', '=', 'purchase_lines.transaction_id')
                        ->where('transactions.business_id', $businessId)
                        ->where('transactions.type', 'purchase_requisition')
                        ->where('purchase_lines.id', $requisitionLineId)
                        ->when($requisitionIds, fn ($query) => $query->whereIn('transactions.id', $requisitionIds))
                        ->exists();
                    if (! $validRequisitionLine) {
                        $validator->errors()->add("purchases.$index.purchase_requisition_line_id", 'A linked requisition line is invalid.');
                    }
                }

                $purchaseLineId = (int) ($line['purchase_line_id'] ?? 0);
                if ($purchaseLineId && ! $this->isMethod('post')) {
                    $validPurchaseLine = PurchaseLine::query()
                        ->join('transactions', 'transactions.id', '=', 'purchase_lines.transaction_id')
                        ->where('transactions.business_id', $businessId)
                        ->where('transactions.type', 'purchase_order')
                        ->where('transactions.id', $routeTransactionId)
                        ->where('purchase_lines.id', $purchaseLineId)
                        ->exists();
                    if (! $validPurchaseLine) {
                        $validator->errors()->add("purchases.$index.purchase_line_id", 'A purchase-order line is invalid.');
                    }
                }
                if (in_array($variationId, $seenVariations, true)) {
                    $validator->errors()->add("purchases.$index.variation_id", 'Duplicate product lines are not allowed.');
                }
                $seenVariations[] = $variationId;
            }
        });
    }
}
