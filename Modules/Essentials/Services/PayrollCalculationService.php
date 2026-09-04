<?php

namespace Modules\Essentials\Services;

use App\Utils\ModuleUtil;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    private ModuleUtil $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Calculate an employee payroll from editable inputs on the server.
     * Submitted totals and percentage-derived amounts are intentionally
     * ignored so browser-side changes cannot control the posted net amount.
     *
     * @return array<string, mixed>
     */
    public function calculate(array $payload): array
    {
        $duration = $this->decimal($payload['essentials_duration'] ?? null, 'essentials_duration');
        $rate = $this->decimal(
            $payload['essentials_amount_per_unit_duration'] ?? null,
            'essentials_amount_per_unit_duration'
        );

        if ($duration < 0 || $rate < 0) {
            throw ValidationException::withMessages([
                'payrolls' => __('validation.min.numeric', ['attribute' => 'payroll amount', 'min' => 0]),
            ]);
        }

        $baseAmount = round($duration * $rate, 4);
        $allowances = $this->components($payload, 'allowance', $baseAmount);
        $deductions = $this->components($payload, 'deduction', $baseAmount);
        $netAmount = round($baseAmount + $allowances['total'] - $deductions['total'], 4);

        if ($netAmount < 0) {
            throw ValidationException::withMessages([
                'payrolls' => 'Payroll deductions cannot exceed base pay plus allowances.',
            ]);
        }

        return [
            'essentials_duration' => $duration,
            'essentials_duration_unit' => mb_substr((string) ($payload['essentials_duration_unit'] ?? ''), 0, 20),
            'essentials_amount_per_unit_duration' => $rate,
            'essentials_allowances' => json_encode($allowances['data']),
            'essentials_deductions' => json_encode($deductions['data']),
            'total_before_tax' => $netAmount,
            'final_total' => $netAmount,
            'staff_note' => mb_substr((string) ($payload['staff_note'] ?? ''), 0, 2000),
        ];
    }

    /**
     * @return array{data: array<string, array<int, mixed>>, total: float}
     */
    private function components(array $payload, string $kind, float $baseAmount): array
    {
        $names = (array) ($payload[$kind.'_names'] ?? []);
        $types = (array) ($payload[$kind.'_types'] ?? []);
        $submittedAmounts = (array) ($payload[$kind.'_amounts'] ?? []);
        $submittedPercents = (array) ($payload[$kind.'_percent'] ?? []);

        $output = [
            $kind.'_names' => [],
            $kind.'_amounts' => [],
            $kind.'_types' => [],
            $kind.'_percents' => [],
        ];
        $total = 0.0;

        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $type = (string) ($types[$index] ?? 'fixed');
            if (! in_array($type, ['fixed', 'percent'], true)) {
                throw ValidationException::withMessages([
                    'payrolls' => 'Each payroll component must be fixed or percent.',
                ]);
            }

            $percent = 0.0;
            if ($type === 'percent') {
                $percent = $this->decimal($submittedPercents[$index] ?? null, $kind.'_percent');
                if ($percent < 0 || $percent > 100) {
                    throw ValidationException::withMessages([
                        'payrolls' => 'Payroll component percentages must be between 0 and 100.',
                    ]);
                }
                $amount = round($baseAmount * $percent / 100, 4);
            } else {
                $amount = $this->decimal($submittedAmounts[$index] ?? 0, $kind.'_amount');
                if ($amount < 0) {
                    throw ValidationException::withMessages([
                        'payrolls' => 'Payroll component amounts cannot be negative.',
                    ]);
                }
            }

            $output[$kind.'_names'][] = mb_substr($name, 0, 255);
            $output[$kind.'_amounts'][] = $amount;
            $output[$kind.'_types'][] = $type;
            $output[$kind.'_percents'][] = $percent;
            $total = round($total + $amount, 4);
        }

        return ['data' => $output, 'total' => $total];
    }

    private function decimal($value, string $field): float
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                'payrolls' => "The {$field} value is required.",
            ]);
        }

        $decimal = $this->moduleUtil->num_uf($value);
        if (! is_numeric($decimal) || ! is_finite((float) $decimal)) {
            throw ValidationException::withMessages([
                'payrolls' => "The {$field} value must be numeric.",
            ]);
        }

        return round((float) $decimal, 4);
    }
}
