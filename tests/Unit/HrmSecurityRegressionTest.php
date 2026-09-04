<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class HrmSecurityRegressionTest extends TestCase
{
    public function test_payroll_payment_does_not_collect_or_persist_card_secrets(): void
    {
        $controller = file_get_contents($this->projectPath('Modules/Essentials/Http/Controllers/PayrollController.php'));
        $payment_form = file_get_contents($this->projectPath('Modules/Essentials/Resources/views/payroll/payment_row.blade.php'));

        $this->assertStringNotContainsString('card_security', $controller.$payment_form);
        $this->assertStringNotContainsString('[card_number]', $payment_form);
    }

    public function test_hrm_api_attendance_routes_are_authenticated(): void
    {
        $routes = file_get_contents($this->projectPath('Modules/Essentials/Routes/api.php'));

        $this->assertStringContainsString("middleware('auth:api')", $routes);
        $this->assertStringContainsString('/attendance/status', $routes);
        $this->assertStringContainsString('/attendance/clock', $routes);
    }

    public function test_incomplete_resource_actions_are_not_exposed(): void
    {
        $routes = file_get_contents($this->projectPath('Modules/Essentials/Routes/web.php'));

        $this->assertStringContainsString("->only(['index', 'create', 'store', 'destroy'])", $routes);
        $this->assertStringContainsString("->only(['index', 'create', 'store', 'edit', 'update'])", $routes);
    }

    public function test_hrm_is_a_separate_default_feature_for_all_six_launch_industries(): void
    {
        $profileMigration = file_get_contents($this->projectPath('database/migrations/2026_09_02_000001_add_industry_feature_profiles.php'));
        $separationMigration = file_get_contents($this->projectPath('database/migrations/2026_09_03_000012_separate_hrm_and_hotel_management_features.php'));
        $repairMigration = file_get_contents($this->projectPath('database/migrations/2026_09_03_000013_enable_hrm_for_first_six_industries.php'));
        $onboarding = file_get_contents($this->projectPath('config/industry_onboarding.php'));

        $industries = [
            'general_business',
            'restaurant_food_service',
            'hotel_lodge_guesthouse',
            'hotel_with_restaurant',
            'property_management_rentals',
            'professional_services',
        ];

        foreach ($industries as $industry) {
            $this->assertStringContainsString("'$industry'", $profileMigration);
            $this->assertStringContainsString("'$industry'", $repairMigration);
            $this->assertStringContainsString("'$industry'", $onboarding);
        }

        $this->assertStringContainsString("'hrm' =>", $separationMigration);
        $this->assertStringContainsString("'hms' =>", $separationMigration);
        $this->assertStringContainsString("'module_key' => 'Essentials'", $separationMigration);
        $this->assertStringContainsString("'module_key' => 'Hms'", $separationMigration);
        $this->assertStringContainsString("'enabled_by_default' => true", $repairMigration);
    }

    public function test_hrm_routes_enforce_company_feature_middleware(): void
    {
        $routes = file_get_contents($this->projectPath('Modules/Essentials/Routes/web.php'));

        $this->assertStringContainsString("'business.feature:hrm'", $routes);
    }

    public function test_hrm_authorization_keeps_entitlement_and_role_permission_separate(): void
    {
        $trait = file_get_contents($this->projectPath('Modules/Essentials/Http/Controllers/Concerns/AuthorizesHrmRequests.php'));

        $this->assertStringContainsString("hasThePermissionInSubscription(\$businessId, 'essentials_module')", $trait);
        $this->assertStringContainsString('$user->canForBusiness($ability, $businessId)', $trait);
        $this->assertStringContainsString('authorizeHrmFeature($businessId)', $trait);
    }

    public function test_attendance_inputs_are_tenant_scoped_escaped_and_serialized(): void
    {
        $controller = file_get_contents($this->projectPath('Modules/Essentials/Http/Controllers/AttendanceController.php'));
        $utility = file_get_contents($this->projectPath('Modules/Essentials/Utils/EssentialsUtil.php'));

        $this->assertStringContainsString("->where('u.business_id', \$business_id)", $controller);
        $this->assertStringContainsString("->from('hrm_employment_profiles as hep')", $controller);
        $this->assertStringContainsString("->where('hep.business_id', \$business_id)", $controller);
        $this->assertStringContainsString("->where('es.business_id', '=', \$business_id)", $controller);
        $this->assertStringContainsString('e($row->clock_in_note)', $controller);
        $this->assertStringContainsString('e($row->clock_out_note)', $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);
        $this->assertStringContainsString('->lockForUpdate()', $utility);
    }

    public function test_payroll_is_calculated_server_side_and_payment_is_explicitly_authorized(): void
    {
        $controller = file_get_contents($this->projectPath('Modules/Essentials/Http/Controllers/PayrollController.php'));
        $calculator = file_get_contents($this->projectPath('Modules/Essentials/Services/PayrollCalculationService.php'));

        $this->assertStringContainsString("authorizeHrmAction(\$business_id, 'essentials.pay_payroll')", $controller);
        $this->assertStringContainsString("->where('type', 'payroll')", $controller);
        $this->assertStringContainsString('payrollCalculationService->calculate($payroll)', $controller);
        $this->assertStringContainsString("'final_total' => \$netAmount", $calculator);
        $this->assertStringNotContainsString("'card_security'", $controller);
    }

    public function test_leave_decisions_are_scoped_locked_and_attributed(): void
    {
        $controller = file_get_contents($this->projectPath('Modules/Essentials/Http/Controllers/EssentialsLeaveController.php'));

        $this->assertStringContainsString("EssentialsLeave::where('business_id', \$business_id)", $controller);
        $this->assertStringContainsString('->lockForUpdate()', $controller);
        $this->assertStringContainsString('$allowed_transitions', $controller);
        $this->assertStringContainsString('$leave->changed_by = auth()->user()->id', $controller);
    }

    public function test_auto_clock_out_is_registered_for_standard_scheduler_operation(): void
    {
        $provider = file_get_contents($this->projectPath('Modules/Essentials/Providers/EssentialsServiceProvider.php'));
        $command = file_get_contents($this->projectPath('Modules/Essentials/Console/AutoClockOutUser.php'));

        $this->assertStringContainsString('->everyFiveMinutes()', $provider);
        $this->assertStringContainsString('->withoutOverlapping()', $provider);
        $this->assertStringNotContainsString("env('APP_ENV') == 'live'", $provider);
        $this->assertStringContainsString("->whereColumn('es.business_id', 'essentials_attendances.business_id')", $command);
        $this->assertStringContainsString('Carbon::now($timezone)', $command);
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
