<?php

namespace App\Services;

use App\Business;
use App\BusinessLocation;
use App\Property;
use App\PropertyDashboardPreference;
use App\PropertyLease;
use App\PropertyMaintenanceTicket;
use App\PropertyRentDue;
use App\PropertyUnit;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PropertyDashboardService
{
    public function preference(int $businessId, int $userId): PropertyDashboardPreference
    {
        $preference = PropertyDashboardPreference::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->first();

        if ($preference) {
            return $preference;
        }

        return new PropertyDashboardPreference([
            'business_id' => $businessId,
            'user_id' => $userId,
            'visible_widgets' => config('property_management.default_widgets', []),
            'widget_order' => config('property_management.default_widgets', []),
            'compact_mode' => false,
        ]);
    }

    public function availableWidgets(): array
    {
        return config('property_management.dashboard_widgets', []);
    }

    public function allowedLocations(int $businessId, User $user): Collection
    {
        $query = BusinessLocation::query()
            ->where('business_id', $businessId)
            ->active()
            ->orderBy('name');

        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('id', $permitted);
        }

        return $query->get(['id', 'name', 'city', 'country']);
    }

    public function dashboard(
        Business $business,
        User $user,
        ?int $locationId,
        ?int $propertyId
    ): array {
        $businessId = (int) $business->id;
        $permitted = $user->permitted_locations($businessId);
        $locations = $this->allowedLocations($businessId, $user);

        if ($locationId && ! $locations->contains('id', $locationId)) {
            throw ValidationException::withMessages([
                'location_id' => 'The selected location is not available to your account.',
            ]);
        }

        $propertyBase = Property::query();
        $this->scopeProperties($propertyBase, $businessId, $permitted, $locationId, null, $user);
        $propertyOptions = (clone $propertyBase)
            ->orderBy('name')
            ->get(['id', 'name', 'business_location_id', 'portfolio_category', 'property_subtype']);

        if ($propertyId && ! $propertyOptions->contains('id', $propertyId)) {
            throw ValidationException::withMessages([
                'property_id' => 'The selected property is not available in this location.',
            ]);
        }

        $propertiesQuery = Property::query();
        $this->scopeProperties($propertiesQuery, $businessId, $permitted, $locationId, $propertyId, $user);

        $properties = (clone $propertiesQuery)
            ->with('businessLocation:id,name')
            ->withCount([
                'units',
                'units as occupied_units_count' => fn ($query) => $query->where('status', 'occupied'),
                'units as vacant_units_count' => fn ($query) => $query->where('status', 'vacant'),
            ])
            ->orderBy('name')
            ->get();

        $unitQuery = PropertyUnit::whereHas('property', function ($query) use (
            $businessId,
            $permitted,
            $locationId,
            $propertyId,
            $user
        ) {
            $this->scopeProperties($query, $businessId, $permitted, $locationId, $propertyId, $user);
        });

        $rentQuery = PropertyRentDue::where('business_id', $businessId)
            ->whereHas('lease.unit.property', function ($query) use (
                $businessId,
                $permitted,
                $locationId,
                $propertyId,
                $user
            ) {
                $this->scopeProperties($query, $businessId, $permitted, $locationId, $propertyId, $user);
            });

        $leaseQuery = PropertyLease::where('business_id', $businessId)
            ->whereHas('unit.property', function ($query) use (
                $businessId,
                $permitted,
                $locationId,
                $propertyId,
                $user
            ) {
                $this->scopeProperties($query, $businessId, $permitted, $locationId, $propertyId, $user);
            });

        $maintenanceQuery = PropertyMaintenanceTicket::where('business_id', $businessId)
            ->whereHas('unit.property', function ($query) use (
                $businessId,
                $permitted,
                $locationId,
                $propertyId,
                $user
            ) {
                $this->scopeProperties($query, $businessId, $permitted, $locationId, $propertyId, $user);
            });

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $monthlyRent = (clone $rentQuery)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        $units = $unitQuery->count();
        $occupied = (clone $unitQuery)->where('status', 'occupied')->count();
        $monthlyBilled = (float) (clone $monthlyRent)->sum('amount_due');
        $monthlyCollected = (float) (clone $monthlyRent)->sum('amount_paid');

        $outstandingDues = (clone $rentQuery)
            ->whereIn('status', ['due', 'partial'])
            ->get(['due_date', 'amount_due', 'amount_paid']);

        $chart = collect(range(5, 0))->map(function ($monthsAgo) use ($rentQuery, $today) {
            $month = $today->copy()->subMonths($monthsAgo);
            $query = (clone $rentQuery)->whereBetween('due_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ]);

            return [
                'label' => $month->format('M Y'),
                'billed' => (float) (clone $query)->sum('amount_due'),
                'collected' => (float) (clone $query)->sum('amount_paid'),
            ];
        })->values();

        $onboardingAnswers = (array) data_get($business->onboarding_settings, 'answers', []);

        return [
            'locations' => $locations,
            'propertyOptions' => $propertyOptions,
            'properties' => $properties,
            'selectedLocationId' => $locationId,
            'selectedPropertyId' => $propertyId,
            'profile' => [
                'operating_model' => data_get(
                    config('property_management.operating_models', []),
                    $onboardingAnswers['operating_model'] ?? '',
                    'Property management business'
                ),
                'portfolio_category' => data_get(
                    config('property_management.portfolio_categories', []),
                    $onboardingAnswers['portfolio_category'] ?? '',
                    'Mixed property portfolio'
                ),
                'property_subtypes' => collect($onboardingAnswers['property_subtypes'] ?? [])
                    ->map(fn ($subtype) => data_get(config('property_management.property_subtypes', []), $subtype.'.label'))
                    ->filter()
                    ->values(),
            ],
            'summary' => [
                'properties' => $properties->count(),
                'units' => $units,
                'occupied' => $occupied,
                'vacant' => (clone $unitQuery)->where('status', 'vacant')->count(),
                'occupancy_rate' => $units > 0 ? round(($occupied / $units) * 100, 1) : 0,
                'active_tenants' => (clone $leaseQuery)->where('status', 'active')->whereNotNull('contact_id')->distinct('contact_id')->count('contact_id'),
                'rent_billed' => $monthlyBilled,
                'rent_paid' => $monthlyCollected,
                'rent_outstanding' => max(0, $monthlyBilled - $monthlyCollected),
                'collection_rate' => $monthlyBilled > 0 ? round(($monthlyCollected / $monthlyBilled) * 100, 1) : 0,
                'rent_overdue' => $outstandingDues
                    ->filter(fn ($due) => $due->due_date->lt($today))
                    ->sum(fn ($due) => max(0, (float) $due->amount_due - (float) $due->amount_paid)),
                'leases_expiring' => (clone $leaseQuery)
                    ->where('status', 'active')
                    ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(60)->toDateString()])
                    ->count(),
                'maintenance_open' => (clone $maintenanceQuery)->whereIn('status', ['open', 'in_progress'])->count(),
            ],
            'unitStatus' => (clone $unitQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'rentChart' => $chart,
            'receivablesAgeing' => $this->ageOutstanding($outstandingDues, $today),
            'leases' => (clone $leaseQuery)
                ->with(['unit.property.businessLocation', 'tenant'])
                ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('end_date')
                ->latest('id')
                ->take(10)
                ->get(),
            'expiringLeases' => (clone $leaseQuery)
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(90)->toDateString()])
                ->with(['unit.property.businessLocation', 'tenant'])
                ->orderBy('end_date')
                ->take(8)
                ->get(),
            'maintenanceTickets' => (clone $maintenanceQuery)
                ->whereIn('status', ['open', 'in_progress'])
                ->with(['unit.property.businessLocation', 'vendor'])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->latest('reported_on')
                ->take(8)
                ->get(),
        ];
    }

    public function validateDefaults(
        int $businessId,
        User $user,
        ?int $locationId,
        ?int $propertyId
    ): void {
        $locations = $this->allowedLocations($businessId, $user);
        if ($locationId && ! $locations->contains('id', $locationId)) {
            throw ValidationException::withMessages([
                'default_business_location_id' => 'The selected default location is not available to your account.',
            ]);
        }

        if ($propertyId) {
            $permitted = $user->permitted_locations($businessId);
            $query = Property::query();
            $this->scopeProperties($query, $businessId, $permitted, $locationId, $propertyId, $user);
            if (! $query->exists()) {
                throw ValidationException::withMessages([
                    'default_property_id' => 'The selected default property is not available to your account.',
                ]);
            }
        }
    }

    private function scopeProperties(
        Builder $query,
        int $businessId,
        $permittedLocations,
        ?int $locationId,
        ?int $propertyId,
        User $user
    ): Builder {
        app(PropertyAccessService::class)->scopeProperties($query, $businessId, $user, 'view');
        $query->where('properties.is_active', true);

        if ($locationId) {
            $query->where('properties.business_location_id', $locationId);
        }
        if ($propertyId) {
            $query->whereKey($propertyId);
        }

        return $query;
    }

    private function ageOutstanding(Collection $dues, Carbon $today): array
    {
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_plus' => 0.0];

        foreach ($dues as $due) {
            $balance = max(0, (float) $due->amount_due - (float) $due->amount_paid);
            $days = $due->due_date->diffInDays($today, false);
            if ($days <= 0) {
                $buckets['current'] += $balance;
            } elseif ($days <= 30) {
                $buckets['1_30'] += $balance;
            } elseif ($days <= 60) {
                $buckets['31_60'] += $balance;
            } else {
                $buckets['61_plus'] += $balance;
            }
        }

        return $buckets;
    }
}
