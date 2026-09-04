<?php

namespace Tests\Unit;

require_once dirname(__DIR__, 2).'/Modules/Essentials/Services/PayrollCalculationService.php';

use App\Utils\ModuleUtil;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Services\PayrollCalculationService;
use PHPUnit\Framework\TestCase;

class PayrollCalculationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        $container->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $container));
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_it_recalculates_payroll_and_ignores_browser_totals(): void
    {
        $service = $this->service();
        $result = $service->calculate([
            'essentials_duration' => '2',
            'essentials_duration_unit' => 'month',
            'essentials_amount_per_unit_duration' => '100',
            'allowance_names' => ['Transport', 'Performance'],
            'allowance_types' => ['fixed', 'percent'],
            'allowance_amounts' => ['10', '999999'],
            'allowance_percent' => ['0', '10'],
            'deduction_names' => ['Pension'],
            'deduction_types' => ['percent'],
            'deduction_amounts' => ['999999'],
            'deduction_percent' => ['5'],
            'final_total' => '0.01',
        ]);

        $this->assertSame(220.0, $result['final_total']);
        $this->assertSame(220.0, $result['total_before_tax']);
        $this->assertEquals([10.0, 20.0], json_decode($result['essentials_allowances'], true)['allowance_amounts']);
        $this->assertEquals([10.0], json_decode($result['essentials_deductions'], true)['deduction_amounts']);
    }

    public function test_it_rejects_component_percentages_above_one_hundred(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->calculate([
            'essentials_duration' => 1,
            'essentials_amount_per_unit_duration' => 100,
            'allowance_names' => ['Invalid'],
            'allowance_types' => ['percent'],
            'allowance_percent' => [101],
        ]);
    }

    public function test_it_rejects_a_negative_net_payroll(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->calculate([
            'essentials_duration' => 1,
            'essentials_amount_per_unit_duration' => 100,
            'deduction_names' => ['Invalid'],
            'deduction_types' => ['fixed'],
            'deduction_amounts' => [101],
        ]);
    }

    private function service(): PayrollCalculationService
    {
        $module_util = $this->createMock(ModuleUtil::class);
        $module_util->method('num_uf')->willReturnCallback(function ($value) {
            return (float) str_replace(',', '', (string) $value);
        });

        return new PayrollCalculationService($module_util);
    }
}
