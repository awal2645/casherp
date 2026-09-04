<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class DataImportAndPropertyExpansionTest extends TestCase
{
    public function test_import_sources_use_private_storage_and_company_scoped_records(): void
    {
        $config = require $this->path('config/data_imports.php');
        $filesystems = $this->read('config/filesystems.php');
        $migration = $this->read('database/migrations/2026_09_03_000024_create_business_data_import_system.php');
        $model = $this->read('app/BusinessDataImport.php');

        $this->assertSame('imports_private', $config['disk']);
        $this->assertStringContainsString("'imports_private' => [", $filesystems);
        $this->assertStringContainsString("'driver' => 'local'", $filesystems);
        $this->assertStringContainsString("storage_path('app/private')", $filesystems);
        $privateDisk = substr(
            $filesystems,
            strpos($filesystems, "'imports_private' => ["),
            strpos($filesystems, "'public' => [") - strpos($filesystems, "'imports_private' => [")
        );
        $this->assertStringNotContainsString("'url' =>", $privateDisk);
        $this->assertStringNotContainsString("'visibility' => 'public'", $privateDisk);
        $this->assertStringContainsString("Schema::create('business_data_imports'", $migration);
        $this->assertStringContainsString("Schema::create('business_data_import_rows'", $migration);
        $this->assertStringContainsString("Schema::create('business_data_import_events'", $migration);
        $this->assertStringContainsString("where(\$this->qualifyColumn('business_id'), \$businessId)", $model);
    }

    public function test_import_catalog_is_industry_aware_and_hr_is_available_to_all_industries(): void
    {
        $config = require $this->path('config/data_imports.php');
        $employee = $this->read('app/Services/DataImport/Handlers/EmployeeProfileImportHandler.php');
        $properties = $this->read('app/Services/DataImport/Handlers/PropertyImportHandler.php');
        $hms = $this->read('app/Services/DataImport/Handlers/HmsRoomImportHandler.php');
        $registry = $this->read('app/Services/DataImport/DataImportRegistry.php');

        $this->assertCount(7, $config['handlers']);
        $this->assertStringContainsString("'industries' => ['*']", $employee);
        $this->assertStringContainsString('imports never create passwords', strtolower($employee));
        $this->assertStringContainsString("'industries' => ['property_management_rentals']", $properties);
        $this->assertStringContainsString("'hotel_lodge_guesthouse', 'hotel_with_restaurant'", $hms);
        $this->assertStringContainsString('supportsIndustry', $registry);
        $this->assertStringContainsString('canForBusiness', $registry);
    }

    public function test_import_workflow_requires_validation_approval_and_audited_processing(): void
    {
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Http/Controllers/DataImportController.php');
        $service = $this->read('app/Services/DataImport/DataImportService.php');
        $migration = $this->read('database/migrations/2026_09_03_000024_create_business_data_import_system.php');

        foreach (['index', 'create', 'store', 'template', 'show', 'progress', 'source', 'errors', 'commit', 'cancel', 'rollback'] as $route) {
            $this->assertStringContainsString("name('$route')", $routes);
        }
        foreach (['data_import.view', 'data_import.manage', 'data_import.approve', 'data_import.rollback'] as $permission) {
            $this->assertStringContainsString("'$permission'", $migration);
        }
        $this->assertStringContainsString("where('status', 'ready')", $controller);
        $this->assertStringContainsString("'approved_by' => \$request->user()->id", $controller);
        $this->assertStringContainsString("'validation_started'", $service);
        $this->assertStringContainsString("'processing_completed'", $service);
        $this->assertStringContainsString("'rollback_started'", $service);
        $this->assertStringContainsString('lockForUpdate()', $service);
        $this->assertStringContainsString("'rolling_back'", $service);

        foreach (['PrepareDataImport.php', 'ProcessDataImport.php', 'RollbackDataImport.php'] as $job) {
            $this->assertStringContainsString(
                'function failed(Throwable $exception)',
                $this->read('app/Jobs/DataImport/'.$job),
                "$job is missing terminal queue failure handling"
            );
        }
    }

    public function test_saas_packages_control_import_capacity_and_retention(): void
    {
        $migration = $this->read('database/migrations/2026_09_03_000024_create_business_data_import_system.php');
        $limits = $this->read('app/Services/DataImport/DataImportLimitService.php');
        $packageRequest = $this->read('app/Http/Requests/SaveSubscriptionPackageRequest.php');
        $purge = $this->read('app/Console/Commands/PurgeExpiredDataImportFiles.php');
        $kernel = $this->read('app/Console/Kernel.php');

        foreach (['data_import_enabled', 'monthly_import_rows', 'max_import_rows_per_file', 'max_import_file_size_mb', 'concurrent_imports', 'import_rollback_days'] as $field) {
            $this->assertStringContainsString("'$field'", $migration);
            $this->assertStringContainsString("'$field'", $packageRequest);
        }
        $this->assertStringContainsString('active_subscription', $limits);
        $this->assertStringContainsString('monthly row allowance', $limits);
        $this->assertStringContainsString('rows_minimised_at', $purge);
        $this->assertStringContainsString('casherp:purge-expired-import-data', $kernel);
    }

    public function test_legacy_import_entry_points_are_bounded_and_sales_preview_has_no_public_path_token(): void
    {
        foreach ([
            'app/Http/Controllers/ImportProductsController.php',
            'app/Http/Controllers/ImportOpeningStockController.php',
            'app/Http/Controllers/ContactController.php',
            'app/Http/Controllers/SellingPriceGroupController.php',
            'app/Http/Controllers/PurchaseController.php',
            'app/Http/Controllers/ExpenseController.php',
            'app/Http/Controllers/ImportSalesController.php',
        ] as $controller) {
            $this->assertStringContainsString('LegacyImportGuard', $this->read($controller), "$controller is missing the shared import guard");
        }

        $guard = $this->read('app/Services/DataImport/LegacyImportGuard.php');
        $sales = $this->read('app/Http/Controllers/ImportSalesController.php');
        $preview = $this->read('resources/views/import_sales/preview.blade.php');
        $this->assertStringContainsString('assertContentMatchesExtension', $guard);
        $this->assertStringContainsString('rejectRemoteReference', $guard);
        $this->assertStringContainsString("Storage::disk('imports_private')", $sales);
        $this->assertStringContainsString("session()->pull('sales_import_files.'", $sales);
        $this->assertStringContainsString("Form::hidden('file_token'", $preview);
        $this->assertStringNotContainsString("Form::hidden('file_name'", $preview);
    }

    public function test_property_gap_work_reuses_the_existing_module_and_protects_lease_occupancy(): void
    {
        $migration = $this->read('database/migrations/2026_09_03_000025_enhance_property_listings_and_viewings.php');
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Http/Controllers/PropertyManagementController.php');
        $viewings = $this->read('app/Http/Controllers/PropertyViewingController.php');

        $this->assertStringContainsString("Schema::table('properties'", $migration);
        $this->assertStringContainsString("'amenities'", $migration);
        foreach (['listing_purpose', 'asking_price', 'available_from', 'is_listed', 'listing_status'] as $field) {
            $this->assertStringContainsString("'$field'", $migration);
        }
        $this->assertStringContainsString("Schema::create('property_viewing_requests'", $migration);
        $this->assertStringContainsString("name('units.index')", $routes);
        $this->assertStringContainsString("name('viewings.index')", $routes);
        $this->assertStringContainsString('End the lease before changing its occupancy status', $controller);
        $this->assertStringContainsString('Occupancy cannot be set manually', $controller);
        $this->assertStringContainsString('lockForUpdate()', $viewings);
        $this->assertStringContainsString('already has an approved viewing', $viewings);

        $handler = $this->read('app/Services/DataImport/Handlers/PropertyUnitImportHandler.php');
        $abstract = $this->read('app/Services/DataImport/Handlers/AbstractDataImportHandler.php');
        $this->assertStringContainsString('active lease, so its occupancy status must remain occupied', $handler);
        $this->assertStringContainsString('assertCanDeleteCreated', $abstract);
        $this->assertStringContainsString('assertNoDependencies', $abstract);
    }

    public function test_new_blade_views_compile_to_parseable_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        foreach ([
            'resources/views/data_import/index.blade.php',
            'resources/views/data_import/create.blade.php',
            'resources/views/data_import/show.blade.php',
            'resources/views/property/create_property.blade.php',
            'resources/views/property/create_unit.blade.php',
            'resources/views/property/edit_unit.blade.php',
            'resources/views/property/units.blade.php',
            'resources/views/property/viewings.blade.php',
            'resources/views/role/create.blade.php',
            'resources/views/role/edit.blade.php',
            'Modules/Superadmin/Resources/views/packages/create.blade.php',
            'Modules/Superadmin/Resources/views/packages/edit.blade.php',
        ] as $view) {
            $compiled = $compiler->compileString($this->read($view));
            $this->assertNotEmpty($parser->parse($compiled), "$view did not compile to parseable PHP");
        }
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->path($relative));
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
