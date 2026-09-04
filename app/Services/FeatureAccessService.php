<?php

namespace App\Services;

use App\Business;
use Illuminate\Support\Facades\DB;

class FeatureAccessService
{
    public function enabled(string $featureCode, ?int $businessId = null): bool
    {
        $businessId = $businessId ?: (int) session('user.business_id');
        $business = Business::find($businessId);

        // Existing companies are left unchanged until an administrator assigns
        // an industry. This keeps the rollout backward compatible.
        if (empty($business) || empty($business->industry_id)) {
            // Smart Documents cannot safely select terminology or templates
            // without a company industry. Other legacy modules retain their
            // pre-industry behaviour until the company is classified.
            return $featureCode !== 'smart_documents';
        }

        return DB::table('business_features as bf')
            ->join('features as f', 'f.id', '=', 'bf.feature_id')
            ->where('bf.business_id', $businessId)
            ->where('f.code', $featureCode)
            ->where('bf.is_enabled', true)
            ->exists();
    }
}
