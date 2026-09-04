<?php

use App\Services\DataImport\Handlers\ContactImportHandler;
use App\Services\DataImport\Handlers\EmployeeProfileImportHandler;
use App\Services\DataImport\Handlers\HmsPropertyImportHandler;
use App\Services\DataImport\Handlers\HmsRoomImportHandler;
use App\Services\DataImport\Handlers\HmsRoomTypeImportHandler;
use App\Services\DataImport\Handlers\PropertyImportHandler;
use App\Services\DataImport\Handlers\PropertyUnitImportHandler;

return [
    'enabled' => env('DATA_IMPORT_ENABLED', true),
    'disk' => env('DATA_IMPORT_DISK', 'imports_private'),
    'queue' => env('DATA_IMPORT_QUEUE', 'imports'),
    'max_file_size_mb' => (int) env('DATA_IMPORT_MAX_FILE_SIZE_MB', 10),
    'max_rows_per_file' => (int) env('DATA_IMPORT_MAX_ROWS_PER_FILE', 10000),
    'monthly_rows' => (int) env('DATA_IMPORT_MONTHLY_ROWS', 50000),
    'concurrent_imports' => (int) env('DATA_IMPORT_CONCURRENT_IMPORTS', 2),
    'rollback_days' => (int) env('DATA_IMPORT_ROLLBACK_DAYS', 7),
    'source_retention_days' => (int) env('DATA_IMPORT_SOURCE_RETENTION_DAYS', 30),
    'row_retention_days' => (int) env('DATA_IMPORT_ROW_RETENTION_DAYS', 365),
    'preview_rows' => 100,
    'allowed_extensions' => ['csv', 'xlsx', 'xls'],
    'handlers' => [
        ContactImportHandler::class,
        PropertyImportHandler::class,
        PropertyUnitImportHandler::class,
        HmsPropertyImportHandler::class,
        HmsRoomTypeImportHandler::class,
        HmsRoomImportHandler::class,
        EmployeeProfileImportHandler::class,
    ],
    'legacy' => [
        'products' => [
            'label' => 'Products and services',
            'description' => 'Products, services, SKUs, tax and sales pricing.',
            'route' => '/import-products',
            'permission' => 'product.create',
            'industries' => ['*'],
        ],
        'opening_stock' => [
            'label' => 'Opening stock',
            'description' => 'Opening quantities and unit costs by company location.',
            'route' => '/import-opening-stock',
            'permission' => 'product.opening_stock',
            'industries' => ['general_business', 'restaurant_food_service', 'hotel_with_restaurant'],
        ],
        'sales' => [
            'label' => 'Historical sales',
            'description' => 'Historical invoices with preview, mapping and batch reversal.',
            'route' => '/import-sales',
            'permission' => 'sell.create',
            'industries' => ['general_business', 'restaurant_food_service', 'hotel_with_restaurant', 'professional_services'],
        ],
        'expenses' => [
            'label' => 'Expenses',
            'description' => 'Historical expenses by category and company location.',
            'route' => '/import-expense',
            'permission' => 'expense.add',
            'industries' => ['*'],
        ],
        'product_prices' => [
            'label' => 'Product price groups',
            'description' => 'Export, update and re-import selling prices.',
            'route' => '/update-product-price',
            'permission' => 'product.update',
            'industries' => ['general_business', 'restaurant_food_service', 'hotel_with_restaurant'],
        ],
        'attendance' => [
            'label' => 'HR attendance',
            'description' => 'Validated employee clock-in and clock-out records.',
            'route' => '/hrm/attendance#import_attendance_tab',
            'permission' => 'essentials.crud_all_attendance',
            'industries' => ['*'],
        ],
    ],
];
