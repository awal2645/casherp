<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcurementQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('procurement.quote.manage');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'tax_id' => ['nullable', 'integer'],
            'quote_reference' => ['nullable', 'string', 'max:100'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
            'line_prices' => ['required', 'array', 'min:1', 'max:500'],
            'line_prices.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
