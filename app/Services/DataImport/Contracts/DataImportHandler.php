<?php

namespace App\Services\DataImport\Contracts;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;

interface DataImportHandler
{
    public function definition(): array;

    public function prepare(array $row, Business $business, ?BusinessLocation $location = null): array;

    public function importRow(BusinessDataImport $import, array $data): array;

    public function rollbackRow(BusinessDataImport $import, BusinessDataImportRow $row): void;
}
