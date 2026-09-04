<?php

namespace App\Services;

use App\BusinessLocation;
use App\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class RestaurantContextService
{
    public function businessId(): int
    {
        $businessId = (int) session('user.business_id');
        if ($businessId < 1) {
            throw new AuthorizationException('No active company is selected.');
        }

        if (! app(FeatureAccessService::class)->enabled('restaurant_operations', $businessId)) {
            throw new AuthorizationException('Restaurant Operations is not enabled for the active company.');
        }

        return $businessId;
    }

    public function assertLocation(int $businessId, int $locationId, ?User $user = null): BusinessLocation
    {
        $location = BusinessLocation::where('business_id', $businessId)->whereKey($locationId)->first();
        if (! $location) {
            throw ValidationException::withMessages(['location_id' => 'Select a location belonging to the active company.']);
        }

        $user = $user ?: auth()->user();
        if ($user) {
            $permitted = $user->permitted_locations($businessId);
            if ($permitted !== 'all' && ! in_array($locationId, array_map('intval', (array) $permitted), true)) {
                throw new AuthorizationException('You do not have access to this company location.');
            }
        }

        return $location;
    }

    public function permittedLocationIds(int $businessId, ?User $user = null): ?array
    {
        $user = $user ?: auth()->user();
        if (! $user) {
            return null;
        }

        $permitted = $user->permitted_locations($businessId);

        return $permitted === 'all' ? null : array_map('intval', (array) $permitted);
    }
}
