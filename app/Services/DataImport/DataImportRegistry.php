<?php

namespace App\Services\DataImport;

use App\Business;
use App\Services\DataImport\Contracts\DataImportHandler;
use App\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DataImportRegistry
{
    public function handlers(): Collection
    {
        return collect(config('data_imports.handlers', []))
            ->filter(fn ($class) => is_string($class) && class_exists($class))
            ->map(fn ($class) => app($class))
            ->filter(fn ($handler) => $handler instanceof DataImportHandler)
            ->keyBy(fn (DataImportHandler $handler) => $handler->definition()['key']);
    }

    public function handler(string $dataset): DataImportHandler
    {
        $handler = $this->handlers()->get($dataset);
        if (! $handler) {
            throw new InvalidArgumentException('Unknown or unavailable import dataset.');
        }

        return $handler;
    }

    public function availableFor(Business $business, User $user): Collection
    {
        $industry = optional($business->industry)->code ?: 'general_business';

        return $this->handlers()
            ->filter(function (DataImportHandler $handler) use ($business, $user, $industry) {
                $definition = $handler->definition();
                return $this->supportsIndustry($definition, $industry)
                    && $this->canManage($business, $user, $definition['permission'] ?? null);
            })->map(fn (DataImportHandler $handler) => $handler->definition())->values();
    }

    public function visibleFor(Business $business, User $user): Collection
    {
        $industry = optional($business->industry)->code ?: 'general_business';

        return $this->handlers()->filter(function (DataImportHandler $handler) use ($business, $user, $industry) {
            $definition = $handler->definition();
            return $this->supportsIndustry($definition, $industry)
                && $this->canViewDataset($business, $user, $definition['permission'] ?? null);
        })->map(fn (DataImportHandler $handler) => $handler->definition())->values();
    }

    public function legacyFor(Business $business, User $user): Collection
    {
        $industry = optional($business->industry)->code ?: 'general_business';

        return collect(config('data_imports.legacy', []))
            ->filter(fn ($definition) => $this->supportsIndustry($definition, $industry)
                && $this->canUsePermission($business, $user, $definition['permission'] ?? null));
    }

    public function canView(Business $business, User $user): bool
    {
        return $this->isCompanyAdministrator($business, $user)
            || $user->canForBusiness('data_import.view', $business->id)
            || $user->canForBusiness('data_import.manage', $business->id);
    }

    public function canViewDataset(Business $business, User $user, ?string $datasetPermission = null): bool
    {
        return $this->isCompanyAdministrator($business, $user)
            || ($this->canView($business, $user) && $this->canUsePermission($business, $user, $datasetPermission));
    }

    public function canManage(Business $business, User $user, ?string $datasetPermission = null): bool
    {
        if ($this->isCompanyAdministrator($business, $user)) {
            return true;
        }

        return $user->canForBusiness('data_import.manage', $business->id)
            && $this->canUsePermission($business, $user, $datasetPermission);
    }

    public function canApprove(Business $business, User $user, ?string $datasetPermission = null): bool
    {
        return $this->isCompanyAdministrator($business, $user)
            || ($user->canForBusiness('data_import.approve', $business->id)
                && $this->canUsePermission($business, $user, $datasetPermission));
    }

    public function canRollback(Business $business, User $user, ?string $datasetPermission = null): bool
    {
        return $this->isCompanyAdministrator($business, $user)
            || ($user->canForBusiness('data_import.rollback', $business->id)
                && $this->canUsePermission($business, $user, $datasetPermission));
    }

    public function isCompanyAdministrator(Business $business, User $user): bool
    {
        return (int) $business->owner_id === (int) $user->id
            || $user->can('superadmin')
            || $user->hasRole('Admin#'.$business->id);
    }

    private function canUsePermission(Business $business, User $user, ?string $permission): bool
    {
        return ! $permission || $this->isCompanyAdministrator($business, $user)
            || $user->canForBusiness($permission, $business->id);
    }

    private function supportsIndustry(array $definition, string $industry): bool
    {
        $industries = $definition['industries'] ?? ['*'];
        return in_array('*', $industries, true) || in_array($industry, $industries, true);
    }
}
