<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminStoreBusinessRequest extends CompanyOnboardingRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('superadmin');
    }

    public function rules(): array
    {
        return array_merge($this->companyRules(), [
            'surname' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => ['required', 'same:password'],
            'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'package_id' => ['nullable', 'required_with:paid_via', 'integer', 'exists:packages,id'],
            'paid_via' => ['nullable', 'required_with:package_id', 'string', 'max:100'],
            'payment_transaction_id' => ['nullable', 'string', 'max:255'],
            'tax_label_1' => ['nullable', 'string', 'max:50'],
            'tax_number_1' => ['nullable', 'string', 'max:100'],
            'tax_label_2' => ['nullable', 'string', 'max:50'],
            'tax_number_2' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
