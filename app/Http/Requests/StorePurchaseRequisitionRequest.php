<?php

namespace App\Http\Requests;

use App\BusinessLocation;
use App\Category;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StorePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && auth()->user()->can('purchase_requisition.create')
            && auth()->user()->can('procurement.submit');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'purpose' => trim((string) $this->input('purpose')),
            'ref_no' => trim((string) $this->input('ref_no')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $businessId = (int) $this->session()->get('user.business_id');
        $referenceRule = Rule::unique('transactions', 'ref_no')
            ->where(fn ($query) => $query->where('business_id', $businessId)->where('type', 'purchase_requisition'));
        if ($this->route('purchase_requisition')) {
            $referenceRule->ignore((int) $this->route('purchase_requisition'));
        }

        return [
            'location_id' => ['required', 'integer'],
            'ref_no' => ['nullable', 'string', 'max:100', $referenceRule],
            'delivery_date' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'purpose' => ['required', 'string', 'min:10', 'max:3000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'budget_amount' => ['nullable', 'string', 'max:50'],
            'purchases' => ['required', 'array', 'min:1', 'max:500'],
            'purchases.*.variation_id' => ['required', 'integer'],
            'purchases.*.product_id' => ['required', 'integer'],
            'purchases.*.quantity' => ['nullable'],
            'purchases.*.secondary_unit_quantity' => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $businessId = (int) $this->session()->get('user.business_id');
            $util = app(\App\Utils\Util::class);
            if (! BusinessLocation::where('business_id', $businessId)->whereKey($this->integer('location_id'))->exists()) {
                $validator->errors()->add('location_id', 'Select a valid business location.');
            }

            if ($this->filled('department_id') && ! Category::where('business_id', $businessId)
                ->where('category_type', 'hrm_department')->whereKey($this->integer('department_id'))->exists()) {
                $validator->errors()->add('department_id', 'Select a valid department.');
            }

            if ($this->filled('project_id')) {
                $validProject = Schema::hasTable('pjt_projects')
                    && DB::table('pjt_projects')->where('business_id', $businessId)
                        ->where('id', $this->integer('project_id'))->exists();
                if (! $validProject) {
                    $validator->errors()->add('project_id', 'Select a valid project for this company.');
                }
            }

            try {
                $deliveryDate = Carbon::parse($util->uf_date($this->input('delivery_date'), true));
                if ($deliveryDate->lt(now())) {
                    $validator->errors()->add('delivery_date', 'The required date cannot be in the past.');
                }
            } catch (\Throwable $exception) {
                $validator->errors()->add('delivery_date', 'Enter a valid required date and time.');
            }

            if ($this->filled('budget_amount')) {
                $budget = $util->num_uf($this->input('budget_amount'));
                if (! is_numeric($budget) || (float) $budget < 0) {
                    $validator->errors()->add('budget_amount', 'Enter a valid non-negative budget amount.');
                }
            }

            $seenVariations = [];
            foreach ((array) $this->input('purchases', []) as $index => $line) {
                $quantity = $util->num_uf($line['quantity'] ?? 0);
                $secondary = $util->num_uf($line['secondary_unit_quantity'] ?? 0);
                if ((float) $quantity <= 0 && (float) $secondary <= 0) {
                    $validator->errors()->add("purchases.$index.quantity", 'Each requisition line must have a quantity greater than zero.');
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
                if (in_array($variationId, $seenVariations, true)) {
                    $validator->errors()->add("purchases.$index.variation_id", 'Duplicate product lines are not allowed.');
                }
                $seenVariations[] = $variationId;
            }
        });
    }
}
