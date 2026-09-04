<?php

namespace App\Services\DataImport;

use App\BusinessLocation;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class LegacyImportGuard
{
    public function file(Request $request, string $field): UploadedFile
    {
        $limits = app(DataImportLimitService::class)->limits((int) $request->session()->get('user.business_id'));
        $request->validate([
            $field => ['required', 'file', 'max:'.($limits['max_file_size_mb'] * 1024)],
        ]);
        $file = $request->file($field);
        if (! in_array(strtolower($file->getClientOriginalExtension()), config('data_imports.allowed_extensions', []), true)) {
            throw ValidationException::withMessages([$field => 'Use a CSV, XLSX or XLS spreadsheet.']);
        }
        $this->assertContentMatchesExtension($file, $field);

        return $file;
    }

    private function assertContentMatchesExtension(UploadedFile $file, string $field): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = [
            'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv', 'application/octet-stream'],
            'xlsx' => ['application/zip', 'application/x-zip-compressed', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/x-cdf', 'application/octet-stream'],
        ];
        if (! in_array($mime, $allowedMimes[$extension] ?? [], true)) {
            throw ValidationException::withMessages([$field => 'The file content does not match its spreadsheet extension.']);
        }

        if ($extension === 'csv') {
            $limit = app(DataImportLimitService::class)->limits((int) request()->session()->get('user.business_id'))['max_rows_per_file'];
            $handle = fopen($file->getRealPath(), 'rb');
            $rows = 0;
            $prefix = $handle ? fread($handle, 4096) : '';
            if (str_contains($prefix, "\0")) {
                fclose($handle);
                throw ValidationException::withMessages([$field => 'The CSV contains binary data and cannot be imported safely.']);
            }
            if ($handle) {
                rewind($handle);
            }
            while ($handle && fgetcsv($handle) !== false) {
                if (++$rows > $limit + 1) {
                    fclose($handle);
                    throw ValidationException::withMessages([$field => 'The CSV exceeds the per-file limit of '.number_format($limit).' data rows.']);
                }
            }
            if ($handle) {
                fclose($handle);
            }
        }

        if ($extension === 'xlsx' && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            $opened = $zip->open($file->getRealPath()) === true;
            if (! $opened || $zip->locateName('[Content_Types].xml') === false) {
                if ($opened) {
                    $zip->close();
                }
                throw ValidationException::withMessages([$field => 'The XLSX archive is invalid or corrupted.']);
            }
            $uncompressed = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $uncompressed += (int) ($stat['size'] ?? 0);
                if ($uncompressed > 150 * 1024 * 1024) {
                    $zip->close();
                    throw ValidationException::withMessages([$field => 'The XLSX expands beyond the safe processing limit. Split it into smaller files.']);
                }
            }
            $zip->close();
        }
    }

    public function rows(Request $request, array $rows, string $field): void
    {
        $limit = app(DataImportLimitService::class)->limits((int) $request->session()->get('user.business_id'))['max_rows_per_file'];
        if (count($rows) > $limit) {
            throw ValidationException::withMessages([$field => 'This spreadsheet exceeds the per-file limit of '.number_format($limit).' rows.']);
        }
    }

    public function location(Request $request, int $locationId): BusinessLocation
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $location = BusinessLocation::where('business_id', $businessId)->active()->findOrFail($locationId);
        $permitted = $request->user()->permitted_locations($businessId);
        abort_unless($permitted === 'all' || in_array($locationId, array_map('intval', $permitted), true), 403);

        return $location;
    }

    public function rejectRemoteReference(?string $value, string $label, int $row): void
    {
        if ($value && filter_var($value, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'file' => $label.' in row '.$row.' uses a remote URL. For security, upload documents or images separately after import.',
            ]);
        }
    }
}
