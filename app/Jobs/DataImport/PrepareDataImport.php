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

class PrepareDataImport implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;
    public int $uniqueFor = 900;

    public function __construct(public int $importId)
    {
        $this->onQueue(config('data_imports.queue', 'imports'));
    }

    public function uniqueId(): string
    {
        return 'prepare-import-'.$this->importId;
    }

    public function handle(DataImportService $service): void
    {
        $service->prepare($this->importId);
    }

    public function failed(Throwable $exception): void
    {
        BusinessDataImport::whereKey($this->importId)
            ->whereIn('status', ['uploaded', 'validating'])
            ->update([
                'status' => 'failed',
                'failure_message' => Str::limit('Validation worker failed: '.$exception->getMessage(), 4000),
                'completed_at' => now(),
            ]);
    }
}
