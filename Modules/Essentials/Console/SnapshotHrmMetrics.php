<?php

namespace Modules\Essentials\Console;

use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Services\WorkforceMetricsService;

class SnapshotHrmMetrics extends Command
{
    protected $signature = 'pos:snapshotHrmMetrics {--business=} {--date=}';
    protected $description = 'Create company-scoped HR metric snapshots from source records';

    public function handle(WorkforceMetricsService $metrics): int
    {
        if (! Schema::hasTable('hrm_metric_snapshots')) return self::SUCCESS;
        $date = $this->option('date') ?: now()->toDateString();
        $query = Business::query()->select('id');
        if ($this->option('business')) $query->where('id', (int) $this->option('business'));
        $query->orderBy('id')->chunkById(100, function ($businesses) use ($metrics, $date) {
            foreach ($businesses as $business) {
                try { $metrics->snapshot((int) $business->id, $date); }
                catch (\Throwable $exception) { report($exception); $this->warn("Business {$business->id}: {$exception->getMessage()}"); }
            }
        });

        return self::SUCCESS;
    }
}
