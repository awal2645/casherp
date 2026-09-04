<?php

namespace App\Services;

use App\Business;
use App\Industry;
use Illuminate\Support\Facades\DB;

class IndustryFeatureProvisioningService
{
    public function defaultCoreModules(Industry $industry, array $onboardingAnswers = []): array
    {
        $featureCodes = $industry->enabledFeatures()->pluck('features.code')->all();
        $modules = [];

        // Quotations and invoices are universal commercial documents. They
        // must not depend on enabling a retail cash-counter POS interface.
        if (in_array('smart_documents', $featureCodes, true)) {
            $modules[] = 'add_sale';
        }
        if (in_array('pos', $featureCodes, true)) {
            $activities = (array) ($onboardingAnswers['business_activities'] ?? []);
            $needsCounterPos = $industry->code !== 'general_business'
                || empty($onboardingAnswers)
                || in_array('retail_sales', $activities, true);

            $modules[] = 'add_sale';
            if ($needsCounterPos) {
                $modules[] = 'pos_sale';
            }
        }
        if (in_array('purchases', $featureCodes, true) || in_array('procurement', $featureCodes, true)) {
            $modules[] = 'purchases';
        }
        if (in_array('inventory', $featureCodes, true)) {
            $tracksInventory = ! array_key_exists('tracks_inventory', $onboardingAnswers)
                || filter_var($onboardingAnswers['tracks_inventory'], FILTER_VALIDATE_BOOLEAN);
            if ($tracksInventory) {
                $modules = array_merge($modules, ['stock_transfers', 'stock_adjustment']);
            }
        }
        if (in_array('expenses', $featureCodes, true)) {
            $modules[] = 'expenses';
        }
        if (in_array('restaurant_operations', $featureCodes, true)) {
            $usesKitchen = ! array_key_exists('kitchen_workflow', $onboardingAnswers)
                || filter_var($onboardingAnswers['kitchen_workflow'], FILTER_VALIDATE_BOOLEAN);
            if ($usesKitchen) {
                $modules[] = 'kitchen';
            }

            $serviceChannels = (array) ($onboardingAnswers['service_channels'] ?? []);
            $hasDineIn = in_array('dine_in', $serviceChannels, true)
                || ! empty($onboardingAnswers['dine_in']);
            $hasServiceStaff = $hasDineIn
                || in_array('delivery', $serviceChannels, true)
                || in_array('room_service', $serviceChannels, true)
                || ! empty($onboardingAnswers['delivery'])
                || ! empty($onboardingAnswers['room_service']);

            // Existing companies and profiles keep the complete restaurant
            // defaults. New onboarding submissions can narrow the visible
            // service-mode tools without disabling Restaurant itself.
            if (empty($onboardingAnswers)
                || $hasDineIn) {
                $modules[] = 'tables';
                $modules[] = 'service_staff';
            } elseif ($hasServiceStaff) {
                $modules[] = 'service_staff';
            }

            // Restaurant reservations, modifiers, preparation routing and
            // fulfilment channels use the existing core module switches.
            // Keeping these switches here makes industry provisioning work for
            // new companies without coupling Hotel Management to Restaurant.
            $modules[] = 'booking';
            $modules[] = 'modifiers';
            $modules[] = 'types_of_service';
            $modules[] = 'restaurant_operations';
        }

        return array_values(array_unique($modules));
    }

    public function provision(Business $business, Industry $industry): void
    {
        $featureIds = $industry->enabledFeatures()->pluck('features.id')->all();
        $now = now();
        $rows = array_map(fn ($featureId) => [
            'business_id' => $business->id,
            'feature_id' => $featureId,
            'is_enabled' => true,
            'source' => 'industry_default',
            'created_at' => $now,
            'updated_at' => $now,
        ], $featureIds);

        if (! empty($rows)) {
            DB::table('business_features')->upsert($rows, ['business_id', 'feature_id'], ['is_enabled', 'source', 'updated_at']);
        }

        if (in_array('procurement', $industry->enabledFeatures()->pluck('features.code')->all(), true)) {
            $commonSettings = $business->common_settings ?: [];
            $commonSettings['enable_purchase_requisition'] = 1;
            $commonSettings['enable_purchase_order'] = 1;
            $business->common_settings = $commonSettings;
        }

        $onboardingSettings = (array) $business->onboarding_settings;
        $coreModules = $this->defaultCoreModules(
            $industry,
            (array) ($onboardingSettings['answers'] ?? [])
        );
        $business->enabled_modules = array_values(array_unique(array_merge(
            (array) $business->enabled_modules,
            $coreModules
        )));

        if ($business->isDirty()) {
            $business->save();
        }
    }
}
