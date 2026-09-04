<?php

namespace App\Http\Requests;

use App\Rules\ReCaptcha;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Schema;
use App\Services\GoogleSocialIdentityService;
use Modules\Superadmin\Entities\Package;

class RegisterBusinessRequest extends CompanyOnboardingRequest
{
    public function rules(): array
    {
        $pendingGoogle = app(GoogleSocialIdentityService::class)->pending($this);
        $packageRequired = class_exists(Package::class)
            && Schema::hasTable('packages')
            && Package::active()->publiclyAvailable()->exists();

        $rules = array_merge($this->companyRules(), [
            'surname' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:4', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => $pendingGoogle
                ? ['nullable', 'string', Password::min(8)->mixedCase()->numbers()]
                : ['required', 'string', Password::min(8)->mixedCase()->numbers()],
            'confirm_password' => $pendingGoogle
                ? ['nullable', 'same:password']
                : ['required', 'same:password'],
            'language' => ['nullable', 'string', 'max:10'],
            'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'package_id' => [
                Rule::requiredIf($packageRequired),
                'nullable',
                'integer',
                Rule::exists('packages', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                        ->where('is_private', false)
                        ->where(function ($targeting) {
                            $targeting->whereNull('businesses')
                                ->orWhere('businesses', '')
                                ->orWhere('businesses', '[]');
                        })
                ),
            ],
        ]);

        if (config('constants.enable_recaptcha')) {
            $rules['g-recaptcha-response'] = ['required', new ReCaptcha];
        }

        if (! empty($this->registrationTermsEnabled())) {
            $rules['accept_tc'] = ['accepted'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $pendingGoogle = app(GoogleSocialIdentityService::class)->pending($this);
        if ($pendingGoogle) {
            // The verified provider email is authoritative for a social sign-up;
            // never trust a browser-editable email value for account linking.
            $this->merge(['email' => $pendingGoogle['email']]);
        }
    }

    private function registrationTermsEnabled(): bool
    {
        return (bool) \App\System::getProperty('superadmin_enable_register_tc');
    }
}
