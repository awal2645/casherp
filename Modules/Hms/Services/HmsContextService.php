<?php

namespace Modules\Hms\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Modules\Hms\Entities\HmsProperty;

class HmsContextService
{
    public function permittedLocationIds(int $businessId): ?array
    {
        $permitted = auth()->user()->permitted_locations($businessId);

        return $permitted === 'all' ? null : array_map('intval', (array) $permitted);
    }

    public function scopeProperties(Builder $query, int $businessId): Builder
    {
        $locationIds = $this->permittedLocationIds($businessId);

        return $query->where('business_id', $businessId)
            ->when($locationIds !== null, fn (Builder $builder) => $builder->whereIn('location_id', $locationIds));
    }

    public function property(int $businessId, int $propertyId): HmsProperty
    {
        $property = $this->scopeProperties(HmsProperty::query(), $businessId)->find($propertyId);
        if (! $property) {
            throw new AuthorizationException('You do not have access to this hotel property or location.');
        }

        return $property;
    }
}
