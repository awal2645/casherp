<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessLocation;
use App\Jobs\DataImport\PrepareDataImport;
use App\Jobs\DataImport\ProcessDataImport;
use App\Jobs\DataImport\RollbackDataImport;
use App\Services\DataImport\DataImportLimitService;
use App\Services\DataImport\LegacyImportGuard;
use App\Services\DataImport\DataImportRegistry;
use App\Services\DataImport\DataImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DataImportController extends Controller
{
    public function __construct(
        private DataImportRegistry $registry,
        private DataImportLimitService $limits,
        private DataImportService $service
    ) {
    }

    public function index(Request $request)
    {
        $business = $this->business($request);
        abort_unless($this->registry->canView($business, $request->user()), 403);
        $visibleDatasets = $this->registry->visibleFor($business, $request->user());
        $request->validate([
            'status' => ['nullable', Rule::in(['uploaded', 'validating', 'ready', 'queued', 'processing', 'completed', 'completed_with_errors', 'failed', 'cancelled', 'rolling_back', 'rolled_back', 'rollback_failed'])],
            'dataset' => ['nullable', 'string', 'max:80'],
        ]);

        $imports = BusinessDataImport::forBusiness($business->id)
            ->whereIn('dataset', $visibleDatasets->pluck('key'))
            ->with(['uploader', 'approver', 'location'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('dataset'), fn ($query) => $query->where('dataset', $request->input('dataset')))
            ->latest()->paginate(25)->withQueryString();

        return view('data_import.index', [
            'business' => $business,
            'imports' => $imports,
            'datasets' => $this->registry->availableFor($business, $request->user()),
            'legacyImports' => $this->registry->legacyFor($business, $request->user()),
            'usage' => $this->limits->usage($business->id),
            'canManage' => $this->registry->canManage($business, $request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $business = $this->business($request);
        $available = $this->registry->availableFor($business, $request->user());
        abort_if($available->isEmpty(), 403);
        $dataset = $request->input('dataset', $available->first()['key'] ?? null);
        $definition = $available->firstWhere('key', $dataset);
        abort_unless($definition, 404);

        return view('data_import.create', [
            'business' => $business,
            'datasets' => $available,
            'definition' => $definition,
            'locations' => BusinessLocation::forDropdown($business->id, false, false, true, true),
            'usage' => $this->limits->usage($business->id),
        ]);
    }

    public function store(Request $request)
    {
        $business = $this->business($request);
        $available = $this->registry->availableFor($business, $request->user());
        $datasetKeys = $available->pluck('key')->all();
        $usage = $this->limits->usage($business->id);
        $data = $request->validate([
            'dataset' => ['required', Rule::in($datasetKeys)],
            'business_location_id' => ['nullable', 'integer'],
            'duplicate_strategy' => ['required', Rule::in(['reject', 'skip', 'update'])],
            'file' => ['required', 'file', 'max:'.($usage['max_file_size_mb'] * 1024)],
            'allow_duplicate_file' => ['nullable', 'boolean'],
        ]);
        $file = app(LegacyImportGuard::class)->file($request, 'file');
        $extension = strtolower($file->getClientOriginalExtension());
        $this->limits->assertUploadAllowed($business->id, (int) $file->getSize());

        $location = null;
        if (! empty($data['business_location_id'])) {
            $location = BusinessLocation::where('business_id', $business->id)->active()->findOrFail($data['business_location_id']);
            $permitted = $request->user()->permitted_locations($business->id);
            abort_unless($permitted === 'all' || in_array((int) $location->id, array_map('intval', $permitted), true), 403);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $duplicate = BusinessDataImport::forBusiness($business->id)
            ->where('dataset', $data['dataset'])->where('checksum', $checksum)
            ->whereNotIn('status', ['failed', 'cancelled', 'rolled_back'])
            ->where('created_at', '>=', now()->subDays(30))->first();
        if ($duplicate && ! $request->boolean('allow_duplicate_file')) {
            throw ValidationException::withMessages([
                'file' => 'This exact file was already uploaded as import '.$duplicate->uuid.'. Select the confirmation checkbox only if re-importing it is intentional.',
            ]);
        }

        $uuid = (string) Str::uuid();
        $disk = config('data_imports.disk', 'local');
        $path = $file->storeAs('data-imports/'.$business->id.'/'.$uuid, 'source.'.$extension, $disk);
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['file' => 'The private application storage could not retain this upload. Contact an administrator.']);
        }
        $originalName = preg_replace('/[\x00-\x1F\x7F]+/u', '_', basename($file->getClientOriginalName()));
        try {
            $import = DB::transaction(function () use ($business, $location, $request, $data, $uuid, $disk, $path, $checksum, $file, $originalName) {
                Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
                $this->limits->assertUploadAllowed($business->id, (int) $file->getSize());
                $duplicate = BusinessDataImport::forBusiness($business->id)
                    ->where('dataset', $data['dataset'])->where('checksum', $checksum)
                    ->whereNotIn('status', ['failed', 'cancelled', 'rolled_back'])
                    ->where('created_at', '>=', now()->subDays(30))->first();
                if ($duplicate && ! $request->boolean('allow_duplicate_file')) {
                    throw ValidationException::withMessages([
                        'file' => 'This exact file was already uploaded as import '.$duplicate->uuid.'. Select the confirmation checkbox only if re-importing it is intentional.',
                    ]);
                }

                return BusinessDataImport::create([
                    'uuid' => $uuid,
                    'business_id' => $business->id,
                    'business_location_id' => optional($location)->id,
                    'uploaded_by' => $request->user()->id,
                    'dataset' => $data['dataset'],
                    'industry_code' => optional($business->industry)->code,
                    'original_name' => Str::limit($originalName ?: 'import.'.$file->getClientOriginalExtension(), 255, ''),
                    'storage_disk' => $disk,
                    'storage_path' => $path,
                    'checksum' => $checksum,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'status' => 'uploaded',
                    'duplicate_strategy' => $data['duplicate_strategy'],
                    'metadata' => [
                        'request_ip' => $request->ip(),
                        'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                        'explicit_duplicate_confirmation' => $request->boolean('allow_duplicate_file'),
                    ],
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        $this->service->event($import, 'uploaded', [
            'dataset' => $import->dataset,
            'file_size' => $import->file_size,
            'checksum_prefix' => substr($import->checksum, 0, 12),
        ], $request->user()->id);
        PrepareDataImport::dispatch($import->id);

        return redirect()->route('data-imports.show', $import)->with('status', [
            'success' => 1,
            'msg' => 'File uploaded securely. Validation is now running.',
        ]);
    }

    public function show(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canViewDataset($business, $request->user(), $definition['permission'] ?? null), 403);

        return view('data_import.show', [
            'business' => $business,
            'import' => $import->load(['uploader', 'approver', 'location', 'events']),
            'rows' => $import->rows()->orderBy('row_number')->paginate(100),
            'definition' => $definition,
            'canApprove' => $this->registry->canApprove($business, $request->user(), $definition['permission'] ?? null),
            'canRollback' => $this->registry->canRollback($business, $request->user(), $definition['permission'] ?? null),
            'rollbackDays' => $this->limits->limits($business->id)['rollback_days'],
        ]);
    }

    public function progress(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canViewDataset($business, $request->user(), $definition['permission'] ?? null), 403);

        return response()->json($import->only([
            'uuid', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'processed_rows',
            'created_rows', 'updated_rows', 'skipped_rows', 'failed_rows', 'failure_message',
        ]));
    }

    public function commit(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canApprove($business, $request->user(), $definition['permission'] ?? null), 403);
        abort_unless($import->status === 'ready' && $import->valid_rows > 0, 422, 'This import is not ready for approval.');

        $updated = BusinessDataImport::whereKey($import->id)->where('status', 'ready')->update([
            'status' => 'queued',
            'approved_by' => $request->user()->id,
        ]);
        abort_unless($updated === 1, 409, 'The import status changed. Refresh the page and try again.');
        $this->service->event($import, 'approved', ['valid_rows' => $import->valid_rows], $request->user()->id);
        ProcessDataImport::dispatch($import->id);

        return redirect()->route('data-imports.show', $import)->with('status', ['success' => 1, 'msg' => 'Import approved and queued for processing.']);
    }

    public function cancel(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canManage($business, $request->user(), $definition['permission'] ?? null), 403);
        abort_unless($import->canBeCancelled(), 422, 'This import can no longer be cancelled.');

        $import->update(['status' => 'cancelled', 'completed_at' => now()]);
        $this->service->event($import, 'cancelled', [], $request->user()->id);

        return redirect()->route('data-imports.show', $import)->with('status', ['success' => 1, 'msg' => 'Import cancelled. No operational records were changed.']);
    }

    public function rollback(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canRollback($business, $request->user(), $definition['permission'] ?? null), 403);
        abort_unless(in_array($import->status, ['completed', 'completed_with_errors', 'rollback_failed'], true), 422, 'This import cannot be rolled back.');

        RollbackDataImport::dispatch($import->id);
        return redirect()->route('data-imports.show', $import)->with('status', ['success' => 1, 'msg' => 'Rollback queued. Records changed after import will be protected and reported.']);
    }

    public function template(Request $request, string $dataset)
    {
        $business = $this->business($request);
        $definition = $this->registry->availableFor($business, $request->user())->firstWhere('key', $dataset);
        abort_unless($definition, 404);
        $columns = array_keys($definition['columns']);
        $examples = collect($definition['columns'])->map(fn ($column) => $column['example'] ?? '')->values()->all();

        return response()->streamDownload(function () use ($columns, $examples) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);
            fputcsv($handle, $examples);
            fclose($handle);
        }, $dataset.'_import_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function source(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canViewDataset($business, $request->user(), $definition['permission'] ?? null), 403);
        abort_unless(Storage::disk($import->storage_disk)->exists($import->storage_path), 404, 'The retained source file is no longer available.');

        return Storage::disk($import->storage_disk)->download($import->storage_path, $import->original_name);
    }

    public function errors(Request $request, BusinessDataImport $data_import)
    {
        $business = $this->business($request);
        $import = $this->scopedImport($business, $data_import);
        $definition = $this->registry->handler($import->dataset)->definition();
        abort_unless($this->registry->canViewDataset($business, $request->user(), $definition['permission'] ?? null), 403);
        $rows = $import->rows()->whereIn('status', ['invalid', 'failed', 'rollback_failed'])->orderBy('row_number')->cursor();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['spreadsheet_row', 'status', 'errors', 'submitted_values']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->row_number,
                    $row->status,
                    $this->safeCsv(collect((array) $row->validation_errors)->flatten()->implode(' | ')),
                    $this->safeCsv(json_encode($row->raw_values, JSON_UNESCAPED_UNICODE)),
                ]);
            }
            fclose($handle);
        }, 'import_'.$import->uuid.'_errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function business(Request $request): Business
    {
        abort_unless(Schema::hasTable('business_data_imports'), 503, 'The data import migration must be run before this feature can be used.');
        return Business::with('industry')->findOrFail((int) $request->session()->get('user.business_id'));
    }

    private function scopedImport(Business $business, BusinessDataImport $import): BusinessDataImport
    {
        abort_unless((int) $import->business_id === (int) $business->id, 404);
        return $import;
    }

    private function safeCsv(?string $value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
