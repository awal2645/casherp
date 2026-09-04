<?php

namespace App\Http\Controllers\Superadmin;

use App\Business;
use App\Feature;
use App\Industry;
use App\Services\IndustryFeatureProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class IndustryFeatureController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');

        return view('superadmin::industry_features.index', [
            'industries' => Industry::with('features')->orderBy('sort_order')->get(),
            'features' => Feature::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Industry $industry, IndustryFeatureProvisioningService $provisioning)
    {
        abort_unless(auth()->user()->can('superadmin'), 403, 'Unauthorized action.');
        $data = $request->validate([
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer', 'exists:features,id'],
            'apply_to_existing' => ['nullable', 'boolean'],
        ]);

        $featureIds = $data['feature_ids'] ?? [];
        DB::transaction(function () use ($industry, $featureIds, $request, $provisioning) {
            $industry->features()->sync($featureIds);

            if ($request->boolean('apply_to_existing')) {
                Business::where('industry_id', $industry->id)->each(function (Business $business) use ($industry, $provisioning) {
                    DB::table('business_features')->where('business_id', $business->id)->delete();
                    $freshIndustry = $industry->fresh();
                    $provisioning->provision($business, $freshIndustry);
                    $onboardingSettings = (array) $business->onboarding_settings;
                    $business->enabled_modules = $provisioning->defaultCoreModules(
                        $freshIndustry,
                        (array) ($onboardingSettings['answers'] ?? [])
                    );
                    $business->save();
                });
            }
        });

        return redirect()->route('superadmin.industry-features.index')->with('status', ['success' => 1, 'msg' => __('lang_v1.success')]);
    }
}
