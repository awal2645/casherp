<?php

namespace App\Services;

use App\Business;
use App\User;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;

class BusinessContextService
{
    private BusinessUtil $businessUtil;

    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;
    }

    public function activate(Request $request, User $user, Business $business): void
    {
        abort_unless($business->is_active, 403, 'The selected company is inactive.');
        abort_unless($user->canAccessBusiness($business->id), 403, 'You do not have access to the selected company.');

        // business_id is the legacy last-active-company pointer. The session is
        // authoritative for this browser so simultaneous sessions can remain
        // in different companies without mixing their request context.
        $user->business_id = $business->id;
        $user->save();
        $user->unsetRelation('business');
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        $request->session()->forget([
            'user',
            'business',
            'currency',
            'financial_year',
            'cd_default_landing_done',
        ]);

        $currency = $business->currency;
        $request->session()->put('user', [
            'id' => $user->id,
            'surname' => $user->surname,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'business_id' => $business->id,
            'language' => $user->language,
        ]);
        $request->session()->put('business', $business);
        $request->session()->put('currency', [
            'id' => $currency->id,
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'thousand_separator' => $currency->thousand_separator,
            'decimal_separator' => $currency->decimal_separator,
        ]);
        $request->session()->put(
            'financial_year',
            $this->businessUtil->getCurrentFinancialYear($business->id)
        );
    }

    public function recoverActiveBusiness(User $user): ?Business
    {
        $current = $user->accessibleBusinesses()
            ->whereKey($user->business_id)
            ->where('is_active', true)
            ->with('currency')
            ->first();

        if (! empty($current)) {
            return $current;
        }

        return $user->accessibleBusinesses()
            ->where('is_active', true)
            ->with('currency')
            ->orderByRaw('CASE WHEN owner_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->first();
    }
}
