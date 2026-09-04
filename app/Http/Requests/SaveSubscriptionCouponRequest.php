<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubscriptionCouponRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('coupon_code')) {
            $this->merge(['coupon_code' => strtoupper(trim((string) $this->input('coupon_code')))]);
        }
    }

    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('superadmin');
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon') ?: $this->route('id');

        return [
            'coupon_code' => ['required', 'alpha_dash', 'max:100', Rule::unique('superadmin_coupons', 'coupon_code')->ignore($couponId)],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount' => ['required', 'numeric', 'min:0.01'],
            'expiry_date' => ['nullable', 'string', 'max:50'],
            'applied_on_packages' => ['nullable', 'array'],
            'applied_on_packages.*' => ['integer', 'distinct', 'exists:packages,id'],
            'applied_on_business' => ['nullable', 'array'],
            'applied_on_business.*' => ['integer', 'distinct', 'exists:business,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('discount_type') === 'percentage' && (float) $this->input('discount') > 100) {
                $validator->errors()->add('discount', 'A percentage discount cannot exceed 100%.');
            }
        });
    }
}
