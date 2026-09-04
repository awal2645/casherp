<?php

namespace App\Jobs\DataImport;

use App\BusinessDataImport;
use App\Services\DataImport\DataImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessDataImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1200;
    public int $uniqueFor = 1800;

    public function __construct(public int $importId)
    {
        $this->onQueue(config('data_imports.queue', 'imports'));
    }

    public function uniqueId(): string
    {
        return 'process-import-'.$this->importId;
    }

    public function handle(DataImportService $service): void
    {
        $service->process($this->importId);
    }

    public function failed(Throwable $exception): void
    {
        BusinessDataImport::whereKey($this->importId)
            ->whereIn('status', ['ready', 'queued', 'processing'])
            ->update([
                'status' => 'failed',
                'failure_message' => Str::limit('Processing worker failed: '.$exception->getMessage(), 4000),
                'completed_at' => now(),
            ]);
    }
}
