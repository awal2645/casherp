<?php

namespace App\Http\Requests;

class StoreAdditionalBusinessRequest extends CompanyOnboardingRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->ownedBusinesses()->exists();
    }

    public function rules(): array
    {
        return $this->companyRules();
    }
}
