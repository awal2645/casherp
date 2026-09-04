<?php

namespace App\Services;

use App\BusinessDocument;
use App\Property;
use App\PropertyLease;
use App\PropertyRentDue;
use App\PropertyRentPayment;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Applies company, location and optional property-level access in one place.
 *
 * Backward compatibility is intentional: a property without grants continues
 * to follow normal role permissions and location access. As soon as an admin
 * creates a grant for that property it becomes restricted to matching users or
 * company roles for the selected abilities.
 */
class PropertyAccessService
{
    public const ABILITIES = [
        'view' => 'View property records and dashboard data',
        'manage' => 'Manage property, units and leases',
        'rent' => 'Manage rent, payments and financial documents',
        'maintenance' => 'Manage maintenance work',
        'viewings' => 'Manage property viewing requests',
        'documents' => 'Create, preview, download and share property documents',
        'accounting' => 'View and manage property accounting outputs',
    ];

    public function scopeProperties(Builder $query, int $businessId, User $user, string $ability = 'view'): Builder
    {
        $query->where('properties.business_id', $businessId);
        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('properties.business_location_id', array_map('intval', (array) $permitted));
        }

        if ($this->isBusinessAdmin($user, $businessId) || ! Schema::hasTable('property_access_grants')) {
            return $query;
        }

        $roleIds = $user->allBusinessRoles()
            ->where('roles.business_id', $businessId)
            ->pluck('roles.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $query->where(function (Builder $accessQuery) use ($businessId, $user, $roleIds, $ability) {
            // No grants means legacy location/permission behaviour. The first
            // grant deliberately turns on property-level restriction.
            $accessQuery->whereNotExists(function ($grantQuery) use ($businessId) {
                $grantQuery->selectRaw('1')
                    ->from('property_access_grants as any_pag')
                    ->whereColumn('any_pag.property_id', 'properties.id')
                    ->where('any_pag.business_id', $businessId);
            })->orWhereExists(function ($grantQuery) use ($businessId, $user, $roleIds, $ability) {
                $grantQuery->selectRaw('1')
                    ->from('property_access_grants as pag')
                    ->whereColumn('pag.property_id', 'properties.id')
                    ->where('pag.business_id', $businessId)
                    ->where(function ($subjectQuery) use ($user, $roleIds) {
                        $subjectQuery->where(function ($userQuery) use ($user) {
                            $userQuery->where('pag.subject_type', 'user')
                                ->where('pag.subject_id', $user->id);
                        });
                        if (! empty($roleIds)) {
                            $subjectQuery->orWhere(function ($roleQuery) use ($roleIds) {
                                $roleQuery->where('pag.subject_type', 'role')
                                    ->whereIn('pag.subject_id', $roleIds);
                            });
                        }
                    })
                    ->whereJsonContains('pag.abilities', $ability);
            });
        });
    }

    public function canAccess(Property $property, User $user, int $businessId, string $ability = 'view'): bool
    {
        return $this->scopeProperties(Property::query(), $businessId, $user, $ability)
            ->whereKey($property->id)
            ->exists();
    }

    public function assertAccess(Property $property, User $user, int $businessId, string $ability = 'view'): void
    {
        abort_unless(
            $this->canAccess($property, $user, $businessId, $ability),
            403,
            'This property is outside the locations or property access assigned to your role.'
        );
    }

    public function propertyForSource(int $businessId, string $sourceType, int $sourceId): ?Property
    {
        if ($sourceType === 'property_lease') {
            return optional(PropertyLease::where('business_id', $businessId)->with('unit.property')->findOrFail($sourceId)->unit)->property;
        }
        if ($sourceType === 'property_rent_due') {
            return optional(optional(PropertyRentDue::where('business_id', $businessId)->with('lease.unit.property')->findOrFail($sourceId)->lease)->unit)->property;
        }
        if ($sourceType === 'property_rent_payment') {
            $payment = PropertyRentPayment::where('business_id', $businessId)->with('due.lease.unit.property')->findOrFail($sourceId);

            return optional(optional(optional($payment->due)->lease)->unit)->property;
        }

        return null;
    }

    public function assertSourceAccess(int $businessId, User $user, string $sourceType, int $sourceId, string $ability = 'documents'): void
    {
        $property = $this->propertyForSource($businessId, $sourceType, $sourceId);
        abort_unless($property, 404, 'The property source is no longer available.');
        $this->assertAccess($property, $user, $businessId, $ability);
    }

    public function scopeBusinessDocuments(Builder $query, int $businessId, User $user): Builder
    {
        if ($this->isBusinessAdmin($user, $businessId) || ! Schema::hasTable('property_access_grants')) {
            return $query;
        }

        $propertyIds = $this->scopeProperties(Property::query(), $businessId, $user, 'documents')->select('properties.id');
        $leaseIds = PropertyLease::where('property_leases.business_id', $businessId)
            ->whereHas('unit', fn ($unit) => $unit->whereIn('property_id', clone $propertyIds))
            ->select('property_leases.id');
        $dueIds = PropertyRentDue::where('property_rent_dues.business_id', $businessId)
            ->whereIn('property_lease_id', clone $leaseIds)
            ->select('property_rent_dues.id');
        $paymentIds = PropertyRentPayment::where('property_rent_payments.business_id', $businessId)
            ->whereIn('property_rent_due_id', clone $dueIds)
            ->select('property_rent_payments.id');

        $allowedDirectDocumentIds = BusinessDocument::forBusiness($businessId)
            ->where(function ($documentQuery) use ($leaseIds, $dueIds, $paymentIds) {
                $documentQuery->whereNotIn('source_type', [
                    'property_lease',
                    'property_rent_due',
                    'property_rent_payment',
                    'business_document',
                ])->orWhereNull('source_type')
                    ->orWhere(function ($sourceQuery) use ($leaseIds) {
                        $sourceQuery->where('source_type', 'property_lease')->whereIn('source_id', clone $leaseIds);
                    })->orWhere(function ($sourceQuery) use ($dueIds) {
                        $sourceQuery->where('source_type', 'property_rent_due')->whereIn('source_id', clone $dueIds);
                    })->orWhere(function ($sourceQuery) use ($paymentIds) {
                        $sourceQuery->where('source_type', 'property_rent_payment')->whereIn('source_id', clone $paymentIds);
                    });
            })
            ->select('business_documents.id');

        return $query->where(function ($documentQuery) use ($leaseIds, $dueIds, $paymentIds, $allowedDirectDocumentIds) {
            $documentQuery->whereNotIn('source_type', [
                'property_lease',
                'property_rent_due',
                'property_rent_payment',
                'business_document',
            ])
                ->orWhereNull('source_type')
                ->orWhere(function ($sourceQuery) use ($leaseIds) {
                    $sourceQuery->where('source_type', 'property_lease')->whereIn('source_id', $leaseIds);
                })->orWhere(function ($sourceQuery) use ($dueIds) {
                    $sourceQuery->where('source_type', 'property_rent_due')->whereIn('source_id', $dueIds);
                })->orWhere(function ($sourceQuery) use ($paymentIds) {
                    $sourceQuery->where('source_type', 'property_rent_payment')->whereIn('source_id', $paymentIds);
                })->orWhere(function ($sourceQuery) use ($allowedDirectDocumentIds) {
                    $sourceQuery->where('source_type', 'business_document')
                        ->where(function ($parentQuery) use ($allowedDirectDocumentIds) {
                            $parentQuery->whereIn('parent_document_id', clone $allowedDirectDocumentIds)
                                ->orWhereIn('source_id', clone $allowedDirectDocumentIds);
                        });
                });
        });
    }

    public function isBusinessAdmin(User $user, int $businessId): bool
    {
        return $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
    }
}
