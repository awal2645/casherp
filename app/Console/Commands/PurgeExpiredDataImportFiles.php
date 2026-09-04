<?php

namespace App\Console\Commands;

use App\BusinessDataImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredDataImportFiles extends Command
{
    protected $signature = 'casherp:purge-expired-import-data {--dry-run}';

    protected $description = 'Remove expired import source files and minimise old staged row data while retaining audit summaries.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sourceCutoff = now()->subDays(max(1, (int) config('data_imports.source_retention_days', 30)));
        $rowCutoff = now()->subDays(max(30, (int) config('data_imports.row_retention_days', 365)));
        $deletedFiles = 0;
        $minimisedRows = 0;

        foreach (Storage::disk('imports_private')->allFiles('legacy-imports') as $path) {
            if (Storage::disk('imports_private')->lastModified($path) < now()->subHours(2)->timestamp) {
                $deletedFiles++;
                if (! $dryRun) {
                    Storage::disk('imports_private')->delete($path);
                }
            }
        }

        BusinessDataImport::whereNotIn('status', ['uploaded', 'validating', 'ready', 'queued', 'processing', 'rolling_back'])
            ->where('created_at', '<', $sourceCutoff)->orderBy('id')->chunkById(100, function ($imports) use ($dryRun, &$deletedFiles) {
                foreach ($imports as $import) {
                    if (data_get($import->metadata, 'source_deleted_at')) {
                        continue;
                    }
                    if (Storage::disk($import->storage_disk)->exists($import->storage_path)) {
                        $deletedFiles++;
                        if (! $dryRun) {
                            Storage::disk($import->storage_disk)->delete($import->storage_path);
                        }
                    }
                    if (! $dryRun) {
                        $metadata = (array) $import->metadata;
                        $metadata['source_deleted_at'] = now()->toIso8601String();
                        $import->update(['metadata' => $metadata]);
                    }
                }
            });

        BusinessDataImport::whereNotIn('status', ['uploaded', 'validating', 'ready', 'queued', 'processing', 'rolling_back'])
            ->where('created_at', '<', $rowCutoff)->orderBy('id')->chunkById(100, function ($imports) use ($dryRun, &$minimisedRows) {
                foreach ($imports as $import) {
                    if (data_get($import->metadata, 'rows_minimised_at')) {
                        continue;
                    }

                    $count = $import->rows()->count();
                    $minimisedRows += $count;
                    if (! $dryRun) {
                        if ($count > 0) {
                            $import->rows()->update(['raw_values' => '[]', 'normalized_values' => null, 'previous_values' => null]);
                        }
                        $metadata = (array) $import->metadata;
                        $metadata['rows_minimised_at'] = now()->toIso8601String();
                        $import->update(['metadata' => $metadata]);
                    }
                }
            });

        $this->info(($dryRun ? 'Would remove ' : 'Removed ').$deletedFiles.' source files and '.($dryRun ? 'would minimise ' : 'minimised ').$minimisedRows.' staged rows.');
        return self::SUCCESS;
    }
}
