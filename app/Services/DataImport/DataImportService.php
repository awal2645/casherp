<?php

namespace App\Services\DataImport;

use App\BusinessDataImport;
use App\BusinessDataImportEvent;
use App\BusinessDataImportRow;
use App\Business;
use App\Notifications\DataImportStatusNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class DataImportService
{
    public function __construct(
        private DataImportRegistry $registry,
        private DataImportLimitService $limits
    ) {
    }

    public function prepare(int $importId): void
    {
        $import = BusinessDataImport::with(['business.industry', 'location'])->findOrFail($importId);
        if (! in_array($import->status, ['uploaded', 'validating'], true)) {
            return;
        }

        $import->update(['status' => 'validating', 'failure_message' => null]);
        $this->event($import, 'validation_started');

        try {
            $definition = $this->registry->handler($import->dataset)->definition();
            $rows = Excel::toArray([], $import->storage_path, $import->storage_disk)[0] ?? [];
            if (count($rows) < 2) {
                throw ValidationException::withMessages(['file' => 'The spreadsheet must contain a header row and at least one data row.']);
            }

            $header = array_shift($rows);
            $mapping = $this->headerMapping((array) $header, $definition);
            $missing = collect($definition['columns'])
                ->filter(fn ($column) => (bool) ($column['required'] ?? false))
                ->keys()->diff(array_values($mapping));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'file' => 'Missing required columns: '.$missing->implode(', ').'. Download the current template and try again.',
                ]);
            }

            $rows = collect($rows)->filter(fn ($row) => collect((array) $row)->contains(fn ($value) => $value !== null && trim((string) $value) !== ''))->values();
            DB::transaction(function () use ($import, $rows) {
                Business::whereKey($import->business_id)->lockForUpdate()->firstOrFail();
                $this->limits->assertRowsAllowed($import->business_id, $rows->count(), $import->id);
                $import->update(['total_rows' => $rows->count()]);
            }, 3);

            $import->rows()->delete();
            $seen = [];
            $valid = 0;
            $invalid = 0;
            foreach ($rows as $offset => $values) {
                $raw = [];
                foreach ($mapping as $index => $canonical) {
                    if ($canonical !== null) {
                        $raw[$canonical] = Arr::get((array) $values, $index);
                    }
                }
                $prepared = $this->registry->handler($import->dataset)
                    ->prepare($raw, $import->business, $import->location);
                $errors = $prepared['errors'];
                if (isset($seen[$prepared['fingerprint']])) {
                    $errors['_row'][] = 'This row duplicates spreadsheet row '.$seen[$prepared['fingerprint']].'.';
                } else {
                    $seen[$prepared['fingerprint']] = $offset + 2;
                }
                $status = empty($errors) ? 'valid' : 'invalid';
                $status === 'valid' ? $valid++ : $invalid++;
                BusinessDataImportRow::create([
                    'business_data_import_id' => $import->id,
                    'row_number' => $offset + 2,
                    'raw_values' => $raw,
                    'normalized_values' => $prepared['data'],
                    'validation_errors' => $errors ?: null,
                    'fingerprint' => $prepared['fingerprint'],
                    'status' => $status,
                ]);
            }

            $import->update([
                'status' => $valid > 0 ? 'ready' : 'failed',
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'validated_at' => now(),
                'failure_message' => $valid > 0 ? null : 'No valid rows were found. Correct the reported row errors and upload a new file.',
                'metadata' => array_merge((array) $import->metadata, [
                    'detected_headers' => array_values(array_filter($mapping)),
                    'validation_version' => 1,
                ]),
            ]);
            $this->event($import, 'validation_completed', ['valid_rows' => $valid, 'invalid_rows' => $invalid]);
            $this->notify($import->fresh(), $valid > 0 ? 'Import file is ready for approval.' : 'Import file validation failed.');
        } catch (Throwable $exception) {
            $message = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : 'The spreadsheet could not be validated. Check its format and try again.';
            $import->update(['status' => 'failed', 'failure_message' => Str::limit((string) $message, 4000)]);
            $this->event($import, 'validation_failed', ['message' => Str::limit($exception->getMessage(), 500)]);
            $this->notify($import->fresh(), 'Import file validation failed.');
            Log::warning('Data import validation failed', ['import_id' => $import->id, 'exception' => $exception]);
        }
    }

    public function process(int $importId): void
    {
        $import = BusinessDataImport::with(['business', 'location'])->findOrFail($importId);
        if (! in_array($import->status, ['ready', 'queued', 'processing'], true)) {
            return;
        }

        $import->update(['status' => 'processing', 'started_at' => $import->started_at ?: now(), 'failure_message' => null]);
        $this->event($import, 'processing_started');
        $handler = $this->registry->handler($import->dataset);

        $import->rows()->where('status', 'valid')->orderBy('id')->chunkById(100, function ($rows) use ($import, $handler) {
            foreach ($rows as $row) {
                try {
                    DB::transaction(function () use ($import, $handler, $row) {
                        $locked = BusinessDataImportRow::whereKey($row->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'valid') {
                            return;
                        }
                        $result = $handler->importRow($import, (array) $locked->normalized_values);
                        $locked->update([
                            'status' => $result['status'],
                            'target_type' => $result['target_type'] ?? null,
                            'target_id' => $result['target_id'] ?? null,
                            'previous_values' => $result['previous_values'] ?? null,
                        ]);
                    }, 3);
                } catch (Throwable $exception) {
                    BusinessDataImportRow::whereKey($row->id)->where('status', 'valid')->update([
                        'status' => 'failed',
                        'validation_errors' => ['_processing' => [Str::limit($exception->getMessage(), 1000)]],
                    ]);
                    Log::warning('Data import row failed', ['import_id' => $import->id, 'row_id' => $row->id, 'exception' => $exception]);
                }
            }
        });

        $counts = $import->rows()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $created = (int) ($counts['created'] ?? 0);
        $updated = (int) ($counts['updated'] ?? 0);
        $skipped = (int) ($counts['skipped'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $status = ($failed > 0 || $import->invalid_rows > 0) ? 'completed_with_errors' : 'completed';
        $import->update([
            'status' => $status,
            'processed_rows' => $created + $updated + $skipped + $failed,
            'created_rows' => $created,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'completed_at' => now(),
        ]);
        $this->event($import, 'processing_completed', compact('status', 'created', 'updated', 'skipped', 'failed'));
        $this->notify($import->fresh(), $status === 'completed' ? 'Data import completed successfully.' : 'Data import completed with row errors.');
    }

    public function rollback(int $importId): void
    {
        $import = BusinessDataImport::with(['business', 'location'])->findOrFail($importId);
        if (! in_array($import->status, ['completed', 'completed_with_errors', 'rolling_back', 'rollback_failed'], true)) {
            throw new RuntimeException('Only a completed import can be rolled back.');
        }
        $days = $this->limits->limits($import->business_id)['rollback_days'];
        if ($days === 0 || ! $import->completed_at || $import->completed_at->copy()->addDays($days)->isPast()) {
            throw new RuntimeException('The rollback window for this import has expired.');
        }

        $import->update(['status' => 'rolling_back', 'failure_message' => null]);
        $this->event($import, 'rollback_started');
        $handler = $this->registry->handler($import->dataset);
        $failures = 0;
        $import->rows()->whereIn('status', ['created', 'updated', 'rollback_failed'])->orderByDesc('id')
            ->chunkById(100, function ($rows) use ($import, $handler, &$failures) {
                foreach ($rows as $row) {
                    try {
                        DB::transaction(function () use ($import, $handler, $row) {
                            $locked = BusinessDataImportRow::whereKey($row->id)->lockForUpdate()->firstOrFail();
                            $handler->rollbackRow($import, $locked);
                            $locked->update(['status' => 'rolled_back']);
                        }, 3);
                    } catch (Throwable $exception) {
                        $failures++;
                        $row->update([
                            'status' => 'rollback_failed',
                            'validation_errors' => ['_rollback' => [Str::limit($exception->getMessage(), 1000)]],
                        ]);
                    }
                }
            }, 'id', 'id');

        $import->update([
            'status' => $failures > 0 ? 'rollback_failed' : 'rolled_back',
            'rolled_back_at' => $failures > 0 ? null : now(),
            'failure_message' => $failures > 0 ? $failures.' rows could not be rolled back because their records changed or have dependencies.' : null,
        ]);
        $this->event($import, $failures > 0 ? 'rollback_failed' : 'rollback_completed', ['failed_rows' => $failures]);
        $this->notify($import->fresh(), $failures > 0 ? 'Import rollback needs attention.' : 'Import rollback completed.');
    }

    public function event(BusinessDataImport $import, string $event, array $context = [], ?int $userId = null): void
    {
        BusinessDataImportEvent::create([
            'business_data_import_id' => $import->id,
            'business_id' => $import->business_id,
            'user_id' => $userId,
            'event' => $event,
            'context' => $context ?: null,
            'ip_address' => data_get($import->metadata, 'request_ip'),
            'user_agent' => data_get($import->metadata, 'user_agent'),
            'created_at' => now(),
        ]);
    }

    private function headerMapping(array $headers, array $definition): array
    {
        $aliases = [];
        foreach ($definition['columns'] as $key => $column) {
            foreach (array_merge([$key], $column['aliases'] ?? []) as $alias) {
                $aliases[$this->headerKey($alias)] = $key;
            }
        }
        $used = [];
        $mapping = [];
        foreach ($headers as $index => $header) {
            $key = $aliases[$this->headerKey((string) $header)] ?? null;
            if ($key && isset($used[$key])) {
                throw ValidationException::withMessages(['file' => 'The column '.$key.' appears more than once.']);
            }
            if ($key) {
                $used[$key] = true;
            }
            $mapping[$index] = $key;
        }

        return $mapping;
    }

    private function headerKey(string $value): string
    {
        return Str::snake(preg_replace('/[^A-Za-z0-9]+/', ' ', Str::ascii(trim($value))));
    }

    private function notify(BusinessDataImport $import, string $message): void
    {
        try {
            $recipients = collect([$import->uploader, optional($import->business)->owner])->filter()->unique('id');
            Notification::send($recipients, new DataImportStatusNotification($import, $message));
        } catch (Throwable $exception) {
            Log::notice('Data import notification could not be stored', ['import_id' => $import->id, 'message' => $exception->getMessage()]);
        }
    }
}
