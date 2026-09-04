<?php

namespace App\Http\Controllers;

use App\Industry;
use App\Http\Requests\StoreAdditionalBusinessRequest;
use App\Services\AccountBusinessQuotaService;
use App\Services\BusinessContextService;
use App\Services\IndustryFeatureProvisioningService;
use App\Services\IndustryOnboardingService;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Superadmin\Entities\Subscription;
use Spatie\Permission\Models\Permission;

class AdditionalBusinessController extends Controller
{
    public function create(
        Request $request,
        BusinessUtil $businessUtil,
        AccountBusinessQuotaService $quotaService,
        IndustryOnboardingService $onboardingService
    )
    {
        abort_unless($request->user()->ownedBusinesses()->exists(), 403, 'Only a company owner can add another company.');

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = __('business.months.'.$i);
        }

        $industryModels = $onboardingService->selectableIndustries();

        return view('business.create_additional', [
            'industries' => $industryModels->pluck('name', 'id'),
            'industryProfiles' => $onboardingService->profilePayload($industryModels),
            'currencies' => $businessUtil->allCurrencies(),
            'timezone_list' => $businessUtil->allTimeZones(),
            'months' => $months,
            'accounting_methods' => $businessUtil->allAccountingMethods(),
            'quota' => $quotaService->usageFor($request->user()),
        ]);
    }

    public function store(
        StoreAdditionalBusinessRequest $request,
        BusinessUtil $businessUtil,
        AccountBusinessQuotaService $quotaService,
        IndustryFeatureProvisioningService $featureService,
        IndustryOnboardingService $onboardingService,
        BusinessContextService $context,
        ModuleUtil $moduleUtil
    ): RedirectResponse {
        $data = $request->validated();
        $owner = $request->user();
        $industry = Industry::where('id', $data['industry_id'])->where('is_active', true)->firstOrFail();
        $answers = $onboardingService->sanitizeAnswers($industry, $data['onboarding'] ?? []);

        $business = DB::transaction(function () use ($data, $owner, $industry, $answers, $businessUtil, $featureService, $quotaService) {
            $sourceSubscription = $quotaService->ensureCanCreate($owner, true);
            $newBusiness = $businessUtil->createNewBusiness([
                'name' => $data['name'],
                'owner_id' => $owner->id,
                'industry_id' => $industry->id,
                'currency_id' => $data['currency_id'],
                'time_zone' => $data['time_zone'],
                'fy_start_month' => $data['fy_start_month'],
                'accounting_method' => $data['accounting_method'],
                'enabled_modules' => $featureService->defaultCoreModules($industry, $answers),
                'onboarding_settings' => [
                    'version' => 2,
                    'source' => 'additional_company',
                    'industry_code' => $industry->code,
                    'answers' => $answers,
                ],
            ]);

            $featureService->provision($newBusiness, $industry);
            $businessUtil->newBusinessDefaultResources($newBusiness->id, $owner->id);
            $location = $businessUtil->addLocation($newBusiness->id, [
                'name' => $data['name'],
                'country' => $data['country'],
                'state' => $data['state'] ?? null,
                'city' => $data['city'],
                'zip_code' => $data['zip_code'] ?? null,
                'landmark' => $data['landmark'],
                'mobile' => $data['mobile'] ?? null,
            ]);
            Permission::firstOrCreate(['name' => 'location.'.$location->id]);

            // The package is paid once by the account. The new company receives
            // a zero-price coverage record for the same plan dates; it is not a
            // second charge and remains traceable to the paid subscription.
            Subscription::create([
                'business_id' => $newBusiness->id,
                'package_id' => $sourceSubscription->package_id,
                'covered_by_subscription_id' => $sourceSubscription->id,
                'paid_via' => 'account_plan',
                'package_price' => 0,
                'original_price' => 0,
                'package_details' => $sourceSubscription->package_details,
                'start_date' => $sourceSubscription->start_date,
                'end_date' => $sourceSubscription->end_date,
                'trial_end_date' => $sourceSubscription->trial_end_date,
                'status' => 'approved',
                'created_id' => $owner->id,
            ]);

            return $newBusiness;
        });

        if (config('app.env') !== 'demo') {
            try {
                $moduleUtil->getModuleData('after_business_created', ['business' => $business]);
            } catch (\Throwable $exception) {
                \Log::error('Additional company created but a module onboarding hook failed.', [
                    'business_id' => $business->id,
                    'exception' => $exception,
                ]);
            }
        }

        $context->activate($request, $owner, $business->load(['currency', 'industry']));

        return redirect()->route('home')->with('status', [
            'success' => 1,
            'msg' => 'Company created and selected successfully.',
        ]);
    }
}
