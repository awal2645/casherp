<?php

namespace Modules\Essentials\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\EmployeeDocument;
use Modules\Essentials\Entities\PayrollRun;
use Modules\Essentials\Entities\TimeCorrection;

class WorkforceMetricsService
{
    public function calculate(int $businessId, string $startsOn, string $endsOn): array
    {
        $start = Carbon::parse($startsOn)->startOfDay();
        $end = Carbon::parse($endsOn)->endOfDay();
        $baseAt = fn (Carbon $date) => EmploymentProfile::forBusiness($businessId)
            ->where(fn ($q) => $q->whereNull('hire_date')->orWhereDate('hire_date', '<=', $date->toDateString()))
            ->where(fn ($q) => $q->whereNull('termination_date')->orWhereDate('termination_date', '>', $date->toDateString()));
        $headcountStart = $baseAt($start)->count();
        $headcountEnd = $baseAt($end)->count();
        $hires = EmploymentProfile::forBusiness($businessId)->whereBetween('hire_date', [$start->toDateString(), $end->toDateString()])->count();
        $terminations = EmploymentProfile::forBusiness($businessId)->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])->count();
        $averageHeadcount = ($headcountStart + $headcountEnd) / 2;
        $turnoverRate = $averageHeadcount > 0 ? round(($terminations / $averageHeadcount) * 100, 2) : 0;

        $approvedLeaves = DB::table('essentials_leaves')->where('business_id', $businessId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->get();
        $leaveProfiles = EmploymentProfile::forBusiness($businessId)->whereIn('user_id', $approvedLeaves->pluck('user_id')->unique())->get()->keyBy('user_id');
        $absenceDays = $approvedLeaves->sum(function ($leave) use ($start, $end, $leaveProfiles) {
                $from = Carbon::parse($leave->start_date)->max($start);
                $to = Carbon::parse($leave->end_date)->min($end);
                $profile = $leaveProfiles->get($leave->user_id);

                return $profile ? app(LeaveLedgerService::class)->workingDaysFor($profile, $from, $to) : $from->diffInDays($to) + 1;
            });
        $periodLearning = DB::table('hrm_learning_enrollments')->where('business_id', $businessId)->whereBetween('assigned_on', [$start->toDateString(), $end->toDateString()]);
        $assignedLearning = (clone $periodLearning)->count();
        $completedLearning = (clone $periodLearning)->where('status', 'completed')->whereNotNull('completed_at')->where('completed_at', '<=', $end)->count();
        $learningCompletion = $assignedLearning > 0 ? round(($completedLearning / $assignedLearning) * 100, 2) : 0;
        $payrollNet = PayrollRun::forBusiness($businessId)->whereIn('status', ['posted', 'paid', 'closed'])->whereBetween('posted_at', [$start, $end])->sum('net_total');

        return [
            'headcount' => ['value' => $headcountEnd, 'population' => $headcountEnd],
            'new_hires' => ['value' => $hires, 'population' => $headcountEnd],
            'terminations' => ['value' => $terminations, 'population' => $headcountEnd],
            'turnover_rate' => ['value' => $turnoverRate, 'population' => (int) round($averageHeadcount)],
            'approved_absence_days' => ['value' => $absenceDays, 'population' => $headcountEnd],
            'learning_completion_rate' => ['value' => $learningCompletion, 'population' => $assignedLearning],
            'posted_payroll_net' => ['value' => round((float) $payrollNet, 4), 'population' => PayrollRun::forBusiness($businessId)->whereIn('status', ['posted', 'paid', 'closed'])->whereBetween('posted_at', [$start, $end])->sum('employee_count')],
            'pending_time_corrections' => ['value' => TimeCorrection::forBusiness($businessId)->where('status', 'pending')->count(), 'population' => $headcountEnd],
            'documents_expiring_60_days' => ['value' => EmployeeDocument::forBusiness($businessId)->where('status', 'active')->whereBetween('expires_on', [$end->toDateString(), $end->copy()->addDays(60)->toDateString()])->count(), 'population' => $headcountEnd],
            'open_requisitions' => ['value' => DB::table('hrm_job_requisitions')->where('business_id', $businessId)->whereNull('deleted_at')->where('status', 'open')->sum('headcount'), 'population' => null],
        ];
    }

    public function departmentBreakdown(int $businessId, int $minimumGroupSize = 5): array
    {
        return EmploymentProfile::forBusiness($businessId)
            ->leftJoin('categories as department', 'department.id', '=', 'hrm_employment_profiles.department_id')
            ->whereIn('hrm_employment_profiles.employment_status', ['active', 'probation', 'confirmed', 'on_leave'])
            ->groupBy('hrm_employment_profiles.department_id', 'department.name')
            ->selectRaw("COALESCE(department.name, 'Unassigned') as department_name, COUNT(*) as employee_count")
            ->orderBy('department_name')
            ->get()
            ->map(fn ($row) => ['department' => $row->employee_count < $minimumGroupSize ? 'Small group (suppressed)' : $row->department_name, 'employee_count' => (int) $row->employee_count, 'suppressed' => $row->employee_count < $minimumGroupSize])
            ->groupBy('department')
            ->map(fn ($rows, $name) => ['department' => $name, 'employee_count' => $rows->sum('employee_count'), 'suppressed' => (bool) $rows->first()['suppressed']])
            ->values()->all();
    }

    public function snapshot(int $businessId, string $date): void
    {
        $metrics = $this->calculate($businessId, Carbon::parse($date)->startOfMonth()->toDateString(), $date);
        foreach ($metrics as $key => $metric) {
            DB::table('hrm_metric_snapshots')->updateOrInsert(
                ['business_id' => $businessId, 'snapshot_date' => $date, 'metric_key' => $key, 'dimension_type' => 'company', 'dimension_value' => 'all'],
                ['metric_value' => $metric['value'], 'population' => $metric['population'], 'metadata' => json_encode(['source' => 'calculated', 'generated_at' => now()->toIso8601String()]), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
