<?php

namespace App\Services\DataImport;

use App\BusinessDataImport;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Superadmin\Entities\Subscription;

class DataImportLimitService
{
    public function limits(int $businessId): array
    {
        $limits = [
            'enabled' => (bool) config('data_imports.enabled', true),
            'monthly_rows' => (int) config('data_imports.monthly_rows', 50000),
            'max_rows_per_file' => (int) config('data_imports.max_rows_per_file', 10000),
            'max_file_size_mb' => (int) config('data_imports.max_file_size_mb', 10),
            'concurrent_imports' => (int) config('data_imports.concurrent_imports', 2),
            'rollback_days' => (int) config('data_imports.rollback_days', 7),
        ];

        if (! class_exists(Subscription::class) || ! Schema::hasTable('packages')
            || ! Schema::hasColumn('packages', 'data_import_enabled')) {
            return $limits;
        }

        $subscription = Subscription::active_subscription($businessId);
        $package = $subscription ? $subscription->package : null;
        if (! $package) {
            return $limits;
        }

        return [
            'enabled' => (bool) $package->data_import_enabled,
            'monthly_rows' => (int) $package->monthly_import_rows,
            'max_rows_per_file' => (int) $package->max_import_rows_per_file,
            'max_file_size_mb' => (int) $package->max_import_file_size_mb,
            'concurrent_imports' => (int) $package->concurrent_imports,
            'rollback_days' => (int) $package->import_rollback_days,
        ];
    }

    public function usage(int $businessId): array
    {
        $limits = $this->limits($businessId);
        $used = (int) BusinessDataImport::forBusiness($businessId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->sum('total_rows');
        $active = (int) BusinessDataImport::forBusiness($businessId)
            ->whereIn('status', ['uploaded', 'validating', 'ready', 'queued', 'processing', 'rolling_back'])
            ->count();

        return $limits + [
            'used_rows' => $used,
            'remaining_rows' => $limits['monthly_rows'] === 0 ? null : max(0, $limits['monthly_rows'] - $used),
            'active_imports' => $active,
        ];
    }

    public function assertUploadAllowed(int $businessId, int $fileSize): array
    {
        $usage = $this->usage($businessId);
        if (! $usage['enabled']) {
            throw ValidationException::withMessages(['file' => 'Data imports are not enabled for the current subscription.']);
        }
        if ($fileSize > $usage['max_file_size_mb'] * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'The file exceeds the package limit of '.$usage['max_file_size_mb'].' MB.']);
        }
        if ($usage['active_imports'] >= $usage['concurrent_imports']) {
            throw ValidationException::withMessages(['file' => 'The company has reached its concurrent import limit. Finish or cancel an active import first.']);
        }

        return $usage;
    }

    public function assertRowsAllowed(int $businessId, int $rowCount, ?int $excludeImportId = null): void
    {
        $usage = $this->usage($businessId);
        if ($excludeImportId) {
            $usage['used_rows'] -= (int) BusinessDataImport::whereKey($excludeImportId)->value('total_rows');
            if ($usage['monthly_rows'] !== 0) {
                $usage['remaining_rows'] = max(0, $usage['monthly_rows'] - $usage['used_rows']);
            }
        }
        if ($rowCount > $usage['max_rows_per_file']) {
            throw ValidationException::withMessages(['file' => 'This file has '.$rowCount.' data rows; the per-file limit is '.$usage['max_rows_per_file'].'.']);
        }
        if ($usage['monthly_rows'] !== 0 && ($usage['used_rows'] + $rowCount) > $usage['monthly_rows']) {
            throw ValidationException::withMessages(['file' => 'This import would exceed the company monthly row allowance.']);
        }
    }
}
