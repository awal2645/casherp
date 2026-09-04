<?php

namespace App\Services;

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OnboardingProgressService
{
    public function forBusiness(int $businessId, User $user): ?array
    {
        $business = Business::with('industry')->find($businessId);
        if (empty($business) || empty($business->industry_id)) {
            return null;
        }

        $isBusinessAdmin = (int) $business->owner_id === (int) $user->id
            || $user->hasRole('Admin#'.$business->id);
        if (! $isBusinessAdmin) {
            return null;
        }

        $steps = [
            [
                'key' => 'company_profile',
                'label' => 'Company profile and industry',
                'help' => 'Confirm the legal name, business address, currency, time zone, and industry.',
                'complete' => ! empty($business->name)
                    && ! empty($business->currency_id)
                    && ! empty($business->time_zone)
                    && ! empty($business->industry_id),
                'url' => route('business.getBusinessSettings'),
                'required' => true,
            ],
            $this->locationStep($business),
            $this->workspaceStep($business),
            [
                'key' => 'team',
                'label' => 'Invite your team',
                'help' => 'Add staff only when needed and assign the minimum permissions for their work.',
                'complete' => User::where('business_id', $business->id)->where('id', '!=', $user->id)->exists(),
                'url' => route('users.create'),
                'required' => false,
            ],
        ];

        $required = collect($steps)->where('required', true);
        $completedRequired = $required->where('complete', true)->count();
        $percentage = $required->isEmpty()
            ? 100
            : (int) round(($completedRequired / $required->count()) * 100);

        if ($percentage === 100 && empty($business->onboarding_completed_at)) {
            $business->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return [
            'industry' => $business->industry->name,
            'profile' => $this->profileSummary($business),
            'percentage' => $percentage,
            'complete' => $percentage === 100,
            'steps' => $steps,
        ];
    }

    private function workspaceStep(Business $business): array
    {
        $code = optional($business->industry)->code;
        $step = [
            'key' => 'workspace',
            'required' => true,
            'complete' => false,
        ];

        if ($code === 'hotel_lodge_guesthouse' || $code === 'hotel_with_restaurant') {
            return $step + [
                'label' => 'Add your first room',
                'help' => 'Create room types, rooms, prices, and housekeeping readiness.',
                'complete' => $this->businessHasJoinedRecord(
                    'hms_rooms',
                    'hms_room_types',
                    'hms_rooms.hms_room_type_id',
                    'hms_room_types.id',
                    $business->id
                ),
                'url' => url('/hms/rooms/create'),
            ];
        }

        if ($code === 'property_management_rentals') {
            return $step + [
                'label' => 'Add your first property',
                'help' => 'Create a property and its units before adding tenants and leases.',
                'complete' => Schema::hasTable('properties')
                    && DB::table('properties')->where('business_id', $business->id)->exists(),
                'url' => route('property.properties.create'),
            ];
        }

        if ($code === 'professional_services') {
            $hasProject = Schema::hasTable('pjt_projects')
                && DB::table('pjt_projects')->where('business_id', $business->id)->exists();

            return $step + [
                'label' => 'Add your first client or project',
                'help' => 'Create the first client relationship and organise delivery around a project.',
                'complete' => $hasProject || DB::table('contacts')
                    ->where('business_id', $business->id)
                    ->where('is_default', 0)
                    ->where(function ($query) {
                        $query->where('type', 'customer')->orWhere('type', 'both');
                    })->exists(),
                'url' => route('contacts.create', ['type' => 'customer']),
            ];
        }

        if ($code === 'restaurant_food_service') {
            return $step + [
                'label' => 'Build your menu and selling items',
                'help' => 'Add the first menu item, price, tax treatment, and stock details where applicable.',
                'complete' => DB::table('products')->where('business_id', $business->id)->exists(),
                'url' => route('products.create'),
            ];
        }

        return $step + [
            'label' => 'Add your first product or service',
            'help' => 'Create a real catalogue item so quotations, purchases, stock, and sales can begin.',
            'complete' => DB::table('products')->where('business_id', $business->id)->exists(),
            'url' => route('products.create'),
        ];
    }

    private function locationStep(Business $business): array
    {
        $multipleLocations = (bool) data_get(
            $business->onboarding_settings,
            'answers.multiple_locations',
            false
        );

        return [
            'key' => 'first_branch',
            'label' => $multipleLocations ? 'Business locations' : 'First branch or location',
            'help' => $multipleLocations
                ? 'Review the first location, then add the company’s other branches, outlets, offices, hotels, or portfolio bases within the package limit.'
                : 'Review the first location created during registration and its contact details.',
            'complete' => $business->locations()->exists(),
            'url' => route('business-location.index'),
            'required' => true,
        ];
    }

    private function profileSummary(Business $business): array
    {
        $questions = config(
            'industry_onboarding.profiles.'.optional($business->industry)->code.'.questions',
            []
        );
        $answers = (array) data_get($business->onboarding_settings, 'answers', []);

        return collect($questions)
            ->filter(fn ($question) => in_array($question['type'] ?? 'boolean', ['select', 'multiselect'], true))
            ->map(function ($question) use ($answers) {
                $selected = (array) ($answers[$question['key']] ?? []);
                $labels = collect($question['options'] ?? [])
                    ->filter(fn ($option) => in_array((string) $option['value'], array_map('strval', $selected), true))
                    ->pluck('label')
                    ->values()
                    ->all();

                return [
                    'label' => rtrim($question['label'], '?'),
                    'value' => implode(', ', $labels),
                ];
            })
            ->filter(fn ($item) => $item['value'] !== '')
            ->take(3)
            ->values()
            ->all();
    }

    private function businessHasJoinedRecord(
        string $table,
        string $businessTable,
        string $first,
        string $operatorTarget,
        int $businessId
    ): bool {
        if (! Schema::hasTable($table) || ! Schema::hasTable($businessTable)) {
            return false;
        }

        return DB::table($table)
            ->join($businessTable, $first, '=', $operatorTarget)
            ->where($businessTable.'.business_id', $businessId)
            ->exists();
    }
}
