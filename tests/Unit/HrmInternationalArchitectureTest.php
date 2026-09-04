<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HrmInternationalArchitectureTest extends TestCase
{
    public function test_employment_records_are_company_owned_and_sensitive_fields_are_encrypted(): void
    {
        $migration = $this->read('Modules/Essentials/Database/Migrations/2026_09_03_000002_create_hrm_employment_security_foundation.php');
        $model = $this->read('Modules/Essentials/Entities/EmploymentProfile.php');
        $user = $this->read('app/User.php');

        $this->assertStringContainsString("Schema::create('hrm_employment_profiles'", $migration);
        $this->assertStringContainsString("\$table->unique(['business_id', 'user_id']", $migration);
        foreach (['compensation', 'bank_details', 'tax_identifiers', 'medical_data', 'diversity_data', 'disciplinary_data', 'personal_data'] as $field) {
            $this->assertStringContainsString("'$field' => 'encrypted:array'", $model);
        }
        $this->assertStringContainsString('scopeForBusiness', $user);
        $this->assertStringContainsString('employmentProfiles', $user);
    }

    public function test_payroll_uses_snapshots_versioned_country_rules_and_controlled_transitions(): void
    {
        $migration = $this->read('Modules/Essentials/Database/Migrations/2026_09_03_000003_create_hrm_payroll_governance.php');
        $service = $this->read('Modules/Essentials/Services/PayrollRunWorkflowService.php');
        $controller = $this->read('Modules/Essentials/Http/Controllers/PayrollGovernanceController.php');

        foreach (['hrm_payroll_country_packs', 'hrm_payroll_periods', 'hrm_payroll_runs', 'hrm_payroll_run_items', 'hrm_payroll_inputs', 'hrm_payroll_approvals', 'hrm_payroll_payment_batches'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        $this->assertStringContainsString("'employment_snapshot'", $migration);
        $this->assertStringContainsString("'formula_version'", $migration);
        $this->assertStringContainsString("'review' => ['from' => ['calculated']", $service);
        $this->assertStringContainsString("'approve' => ['from' => ['reviewed']", $service);
        $this->assertStringContainsString("'post' => ['from' => ['approved']", $service);
        $this->assertStringContainsString('Separation of duties', $service);
        $this->assertStringContainsString("where('status', 'approved')", $service);
        $this->assertStringContainsString('professionally reviewed', $controller);
        $this->assertStringNotContainsString('eval(', $service);
    }

    public function test_leave_and_time_architecture_is_append_only_and_approval_scoped(): void
    {
        $migration = $this->read('Modules/Essentials/Database/Migrations/2026_09_03_000004_create_hrm_workforce_and_lifecycle.php');
        $ledger = $this->read('Modules/Essentials/Entities/LeaveLedgerEntry.php');
        $workflow = $this->read('Modules/Essentials/Http/Controllers/EssentialsLeaveController.php');
        $workforce = $this->read('Modules/Essentials/Http/Controllers/WorkforceController.php');

        foreach (['hrm_work_calendars', 'hrm_leave_accounts', 'hrm_leave_ledger_entries', 'hrm_time_corrections', 'hrm_employee_documents', 'hrm_lifecycle_events', 'hrm_checklists'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        $this->assertStringContainsString('Leave ledger entries are immutable', $ledger);
        $this->assertStringContainsString('LeaveLedgerService', $workflow);
        $this->assertStringContainsString('workingDaysFor', $this->read('Modules/Essentials/Services/LeaveLedgerService.php'));
        $this->assertStringContainsString('lockForUpdate()', $workflow);
        $this->assertStringContainsString("Storage::disk('local')", $workforce);
        $this->assertStringContainsString("authorizeHrmAction(\$businessId, 'essentials.manage_workforce')", $workforce);
    }

    public function test_international_talent_domains_have_tenant_scoped_routes_and_permissions(): void
    {
        $migration = $this->read('Modules/Essentials/Database/Migrations/2026_09_03_000005_create_hrm_talent_and_insights.php');
        $routes = $this->read('Modules/Essentials/Routes/web.php');
        $permissions = $this->read('Modules/Essentials/Http/Controllers/DataController.php');
        $controller = $this->read('Modules/Essentials/Http/Controllers/TalentController.php');

        foreach (['hrm_job_requisitions', 'hrm_candidates', 'hrm_job_applications', 'hrm_interviews', 'hrm_performance_cycles', 'hrm_performance_goals', 'hrm_performance_reviews', 'hrm_learning_courses', 'hrm_learning_enrollments', 'hrm_benefit_plans', 'hrm_benefit_enrollments', 'hrm_employee_cases', 'hrm_succession_plans', 'hrm_engagement_surveys', 'hrm_metric_snapshots'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        foreach (['manage_recruitment', 'approve_recruitment', 'manage_performance', 'manage_learning', 'manage_benefits', 'manage_employee_relations', 'manage_succession', 'manage_engagement', 'view_workforce_analytics'] as $permission) {
            $this->assertStringContainsString("essentials.$permission", $permissions);
        }
        $this->assertStringContainsString("Route::get('/talent'", $routes);
        $this->assertStringContainsString("Route::get('/analytics'", $routes);
        $this->assertStringContainsString("Crypt::encryptString(\$data['description'])", $controller);
        $this->assertStringContainsString("A different authorized user must approve this requisition", $controller);
        $this->assertStringContainsString("->where('business_id', \$businessId)", $controller);
    }

    public function test_analytics_are_source_calculated_and_small_groups_are_suppressed(): void
    {
        $metrics = $this->read('Modules/Essentials/Services/WorkforceMetricsService.php');
        $command = $this->read('Modules/Essentials/Console/SnapshotHrmMetrics.php');
        $provider = $this->read('Modules/Essentials/Providers/EssentialsServiceProvider.php');
        $view = $this->read('Modules/Essentials/Resources/views/analytics/index.blade.php');

        $this->assertStringContainsString('EmploymentProfile::forBusiness', $metrics);
        $this->assertStringContainsString("where('status', 'approved')", $metrics);
        $this->assertStringContainsString('minimumGroupSize', $metrics);
        $this->assertStringContainsString('Small group (suppressed)', $metrics);
        $this->assertStringContainsString('pos:snapshotHrmMetrics', $command);
        $this->assertStringContainsString("dailyAt('00:20')", $provider);
        $this->assertStringContainsString('Every number below is calculated', $view);
    }

    public function test_hrm_and_hotel_management_remain_distinct_features(): void
    {
        $migration = $this->read('database/migrations/2026_09_03_000012_separate_hrm_and_hotel_management_features.php');
        $routes = $this->read('Modules/Essentials/Routes/web.php');

        $this->assertStringContainsString("'hrm' =>", $migration);
        $this->assertStringContainsString("'hms' =>", $migration);
        $this->assertStringContainsString("'module_key' => 'Essentials'", $migration);
        $this->assertStringContainsString("'module_key' => 'Hms'", $migration);
        $this->assertStringContainsString("'business.feature:hrm'", $routes);
        $this->assertStringNotContainsString('business.feature:hms', $routes);
    }

    public function test_hr_authorization_is_bound_to_the_active_company(): void
    {
        $user = $this->read('app/User.php');
        $authorizer = $this->read('Modules/Essentials/Http/Controllers/Concerns/AuthorizesHrmRequests.php');
        $provider = $this->read('app/Providers/AuthServiceProvider.php');
        $attendanceApi = $this->read('Modules/Essentials/Http/Controllers/AttendanceApiController.php');

        $this->assertStringContainsString('function canForBusiness(string $ability, int $businessId)', $user);
        $this->assertStringContainsString("where('roles.business_id', \$businessId)", $user);
        $this->assertStringContainsString('(int) $this->business_id === $businessId', $user);
        $this->assertStringContainsString('canForBusiness($ability, $businessId)', $authorizer);
        $this->assertStringContainsString("request()->session()->get('user.business_id')", $provider);
        $this->assertStringContainsString("header('X-Business-Id')", $attendanceApi);
        $this->assertStringContainsString('canAccessBusiness($business_id)', $attendanceApi);
        $this->assertStringContainsString("canForBusiness('essentials.allow_users_for_attendance_from_api', \$business_id)", $attendanceApi);
    }

    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
