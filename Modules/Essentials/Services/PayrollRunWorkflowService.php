<?php

namespace Modules\Essentials\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\PayrollApproval;
use Modules\Essentials\Entities\PayrollPaymentAllocation;
use Modules\Essentials\Entities\PayrollPaymentBatch;
use Modules\Essentials\Entities\PayrollRun;
use Modules\Essentials\Entities\PayrollRunItem;

class PayrollRunWorkflowService
{
    private HrmAuditService $audit;

    public function __construct(HrmAuditService $audit)
    {
        $this->audit = $audit;
    }

    public function calculate(PayrollRun $run, int $actorId): PayrollRun
    {
        return DB::transaction(function () use ($run, $actorId) {
            $run = PayrollRun::forBusiness($run->business_id)->lockForUpdate()->findOrFail($run->id);
            if (! in_array($run->status, ['draft', 'calculated'], true)) {
                throw ValidationException::withMessages(['payroll_run' => 'Only a draft or calculated run can be recalculated.']);
            }

            $period = $run->period;
            if (! $period || $period->status === 'closed') {
                throw ValidationException::withMessages(['payroll_period_id' => 'The selected payroll period is unavailable or closed.']);
            }
            if ($run->inputs()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['payroll_run' => 'Approve or reject all pending payroll inputs before calculation.']);
            }

            $profiles = EmploymentProfile::forBusiness($run->business_id)
                ->with('user:id,surname,first_name,last_name,email')
                ->whereIn('employment_status', ['active', 'probation', 'confirmed', 'on_leave'])
                ->when($run->location_id, fn ($query) => $query->where('location_id', $run->location_id))
                ->where(function ($query) use ($period) {
                    $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $period->ends_on);
                })
                ->where(function ($query) use ($period) {
                    $query->whereNull('termination_date')->orWhereDate('termination_date', '>=', $period->starts_on);
                })
                ->orderBy('employee_number')
                ->lockForUpdate()
                ->get();

            if ($profiles->isEmpty()) {
                throw ValidationException::withMessages(['payroll_run' => 'No eligible employees were found for this payroll run.']);
            }

            $pack = $run->countryPack;
            if ($pack && (! $pack->is_active || ! $pack->professionally_reviewed)) {
                throw ValidationException::withMessages(['country_pack_id' => 'Only an active, professionally reviewed country pack can calculate statutory payroll.']);
            }
            if ($pack && ($pack->effective_from->gt($period->ends_on) || ($pack->effective_to && $pack->effective_to->lt($period->starts_on)))) {
                throw ValidationException::withMessages(['country_pack_id' => 'The country pack is not effective for this payroll period.']);
            }

            PayrollRunItem::where('payroll_run_id', $run->id)->delete();
            $totals = ['gross' => 0.0, 'deduction' => 0.0, 'net' => 0.0];
            $varianceValues = [];
            $approvedInputs = $run->inputs()->where('status', 'approved')->orderBy('id')->get()->groupBy('employment_profile_id');
            $priorRunId = PayrollRun::forBusiness($run->business_id)->where('id', '!=', $run->id)->whereIn('status', ['posted', 'paid', 'closed'])->latest('posted_at')->value('id');
            $priorNetByProfile = $priorRunId ? PayrollRunItem::where('payroll_run_id', $priorRunId)->pluck('net_amount', 'employment_profile_id') : collect();

            foreach ($profiles as $profile) {
                $result = $this->calculateEmployee($run, $profile, (array) optional($pack)->rules, $approvedInputs->get($profile->id, collect()), $priorNetByProfile->get($profile->id));
                PayrollRunItem::create($result);
                $totals['gross'] += $result['gross_amount'];
                $totals['deduction'] += $result['deduction_amount'];
                $totals['net'] += $result['net_amount'];
                if ($result['variance_percent'] !== null) {
                    $varianceValues[] = abs((float) $result['variance_percent']);
                }
            }

            $previous = $run->getOriginal();
            $run->update([
                'status' => 'calculated',
                'gross_total' => round($totals['gross'], 4),
                'deduction_total' => round($totals['deduction'], 4),
                'net_total' => round($totals['net'], 4),
                'employee_count' => $profiles->count(),
                'variance_summary' => [
                    'employees_over_10_percent' => collect($varianceValues)->filter(fn ($value) => $value > 10)->count(),
                    'maximum_absolute_percent' => $varianceValues ? max($varianceValues) : 0,
                ],
                'calculated_at' => now(),
                'updated_by' => $actorId,
            ]);
            $this->audit->record($run->business_id, 'payroll.calculated', PayrollRun::class, $run->id, $previous, $run->fresh()->toArray(), 'Server-side payroll calculation');

            return $run->fresh(['period', 'items.profile.user', 'approvals.actor']);
        });
    }

    public function transition(PayrollRun $run, string $action, int $actorId, ?string $comment = null): PayrollRun
    {
        return DB::transaction(function () use ($run, $action, $actorId, $comment) {
            $run = PayrollRun::forBusiness($run->business_id)->lockForUpdate()->findOrFail($run->id);
            $map = [
                'review' => ['from' => ['calculated'], 'to' => 'reviewed', 'stage' => 'review', 'decision' => 'approved'],
                'approve' => ['from' => ['reviewed'], 'to' => 'approved', 'stage' => 'approval', 'decision' => 'approved'],
                'post' => ['from' => ['approved'], 'to' => 'posted', 'stage' => 'posting', 'decision' => 'approved'],
                'close' => ['from' => ['paid'], 'to' => 'closed', 'stage' => 'close', 'decision' => 'approved'],
                'reject' => ['from' => ['calculated', 'reviewed', 'approved'], 'to' => 'draft', 'stage' => 'review', 'decision' => 'rejected'],
            ];
            if (! isset($map[$action]) || ! in_array($run->status, $map[$action]['from'], true)) {
                throw ValidationException::withMessages(['action' => 'This payroll transition is not allowed from the current status.']);
            }
            if (in_array($action, ['approve', 'post'], true) && (int) $run->created_by === $actorId) {
                throw ValidationException::withMessages(['action' => 'Separation of duties requires a different user to approve or post this payroll run.']);
            }
            if ($action === 'post' && (int) $run->approvals()->where('stage', 'approval')->latest('decided_at')->value('actor_user_id') === $actorId) {
                throw ValidationException::withMessages(['action' => 'The payroll approver cannot also post the same payroll run.']);
            }
            if ($action === 'reject' && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'A rejection reason is required.']);
            }
            if ($action === 'approve' && $run->items()->whereNotNull('alerts')->whereJsonLength('alerts', '>', 0)->exists() && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Approval comments are required while payroll alerts remain.']);
            }

            PayrollApproval::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $run->business_id,
                'payroll_run_id' => $run->id,
                'stage' => $map[$action]['stage'],
                'decision' => $map[$action]['decision'],
                'actor_user_id' => $actorId,
                'comment' => $comment,
                'decided_at' => now(),
            ]);
            $previous = $run->toArray();
            $updates = ['status' => $map[$action]['to'], 'updated_by' => $actorId];
            if ($action === 'approve') $updates['approved_at'] = now();
            if ($action === 'post') $updates['posted_at'] = now();
            if ($action === 'close') $updates['closed_at'] = now();
            if ($action === 'reject') {
                $updates['approved_at'] = null;
                $updates['posted_at'] = null;
            }
            $run->update($updates);
            if ($action === 'close' && ! PayrollRun::forBusiness($run->business_id)->where('payroll_period_id', $run->payroll_period_id)->where('id', '!=', $run->id)->where('status', '!=', 'closed')->exists()) {
                $run->period()->update(['status' => 'closed', 'locked_at' => now(), 'locked_by' => $actorId]);
            }
            $this->audit->record($run->business_id, 'payroll.'.$action, PayrollRun::class, $run->id, $previous, $run->fresh()->toArray(), $comment);

            return $run->fresh(['period', 'items.profile.user', 'approvals.actor', 'paymentBatches']);
        });
    }

    public function preparePaymentBatch(PayrollRun $run, array $data, int $actorId): PayrollPaymentBatch
    {
        return DB::transaction(function () use ($run, $data, $actorId) {
            $run = PayrollRun::forBusiness($run->business_id)->with('items.profile')->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'posted') {
                throw ValidationException::withMessages(['payroll_run' => 'Payments can only be prepared for a posted payroll run.']);
            }
            if ($run->paymentBatches()->whereNotIn('status', ['failed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['payroll_run' => 'An active payment batch already exists for this run.']);
            }
            $batch = PayrollPaymentBatch::create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $run->business_id,
                'payroll_run_id' => $run->id,
                'reference' => $data['reference'],
                'payment_method' => $data['payment_method'],
                'account_id' => $data['account_id'] ?? null,
                'currency_code' => $run->currency_code,
                'amount' => $run->net_total,
                'status' => 'prepared',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);
            foreach ($run->items as $item) {
                PayrollPaymentAllocation::create([
                    'business_id' => $run->business_id,
                    'payroll_payment_batch_id' => $batch->id,
                    'payroll_run_item_id' => $item->id,
                    'amount' => $item->net_amount,
                    'status' => 'prepared',
                ]);
            }
            $this->audit->record($run->business_id, 'payroll.payment_batch_prepared', PayrollPaymentBatch::class, $batch->id, [], $batch->toArray(), 'Payment batch prepared');

            return $batch->fresh('allocations.item.profile.user');
        });
    }

    public function reconcilePaymentBatch(PayrollPaymentBatch $batch, array $data, int $actorId): PayrollPaymentBatch
    {
        return DB::transaction(function () use ($batch, $data, $actorId) {
            $batch = PayrollPaymentBatch::forBusiness($batch->business_id)->with('run')->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($batch->status, ['prepared', 'submitted'], true)) {
                throw ValidationException::withMessages(['payment_batch' => 'This payment batch can no longer be reconciled.']);
            }
            $status = $data['status'];
            if (in_array($status, ['reconciled', 'failed'], true) && (int) $batch->created_by === $actorId) {
                throw ValidationException::withMessages(['payment_batch' => 'A different authorized user must reconcile or fail this payment batch.']);
            }
            $batch->update([
                'status' => $status,
                'provider_reference' => $data['provider_reference'] ?? null,
                'reconciliation' => ['recorded_by' => $actorId, 'recorded_at' => now()->toIso8601String(), 'note' => $data['note'] ?? null],
                'submitted_at' => $batch->submitted_at ?: now(),
                'reconciled_at' => in_array($status, ['reconciled', 'failed'], true) ? now() : null,
            ]);
            $allocationStatus = $status === 'reconciled' ? 'paid' : ($status === 'failed' ? 'failed' : 'submitted');
            $batch->allocations()->update(['status' => $allocationStatus]);
            if ($status === 'reconciled') {
                $batch->run->update(['status' => 'paid', 'paid_at' => now(), 'updated_by' => $actorId]);
            }
            $this->audit->record($batch->business_id, 'payroll.payment_batch_'.$status, PayrollPaymentBatch::class, $batch->id, [], $batch->fresh()->toArray(), $data['note'] ?? null);

            return $batch->fresh(['run', 'allocations.item.profile.user']);
        });
    }

    private function calculateEmployee(PayrollRun $run, EmploymentProfile $profile, array $rules, $approvedInputs, $prior): array
    {
        $compensation = (array) $profile->compensation;
        [$base, $calculationAlerts] = $this->baseCompensationForPeriod($run, $profile, $compensation);
        $earnings = [['code' => 'BASE', 'label' => 'Base compensation', 'amount' => round($base, 4)]];
        foreach ((array) ($compensation['allowances'] ?? []) as $allowance) {
            $amount = max(0, (float) ($allowance['amount'] ?? 0));
            if ($amount > 0) {
                $earnings[] = ['code' => mb_substr((string) ($allowance['code'] ?? 'ALLOWANCE'), 0, 40), 'label' => mb_substr((string) ($allowance['label'] ?? 'Allowance'), 0, 100), 'amount' => round($amount, 4)];
            }
        }
        foreach ($approvedInputs->where('input_type', 'earning') as $input) {
            $earnings[] = ['code' => $input->code, 'label' => $input->label, 'amount' => round((float) $input->amount, 4), 'source' => $input->source];
        }
        $gross = collect($earnings)->sum('amount');
        $deductions = [];
        foreach ((array) ($rules['employee_deductions'] ?? []) as $rule) {
            $amount = $this->ruleAmount($gross, (array) $rule);
            if ($amount > 0) {
                $deductions[] = ['code' => mb_substr((string) ($rule['code'] ?? 'STAT'), 0, 40), 'label' => mb_substr((string) ($rule['label'] ?? 'Statutory deduction'), 0, 100), 'amount' => $amount];
            }
        }
        foreach ((array) ($compensation['deductions'] ?? []) as $deduction) {
            $amount = max(0, (float) ($deduction['amount'] ?? 0));
            if ($amount > 0) {
                $deductions[] = ['code' => mb_substr((string) ($deduction['code'] ?? 'DEDUCTION'), 0, 40), 'label' => mb_substr((string) ($deduction['label'] ?? 'Deduction'), 0, 100), 'amount' => round($amount, 4)];
            }
        }
        foreach ($approvedInputs->where('input_type', 'deduction') as $input) {
            $deductions[] = ['code' => $input->code, 'label' => $input->label, 'amount' => round((float) $input->amount, 4), 'source' => $input->source];
        }
        $deductionTotal = min($gross, collect($deductions)->sum('amount'));
        $net = round($gross - $deductionTotal, 4);
        $variance = $prior !== null && (float) $prior !== 0.0 ? round((($net - (float) $prior) / abs((float) $prior)) * 100, 4) : null;
        $alerts = $calculationAlerts;
        if ($base <= 0) $alerts[] = ['code' => 'MISSING_COMPENSATION', 'message' => 'Base compensation is zero or missing.'];
        if (empty($profile->bank_details)) $alerts[] = ['code' => 'MISSING_BANK', 'message' => 'Bank details are missing.'];
        if (abs((float) $variance) > 10) $alerts[] = ['code' => 'NET_VARIANCE', 'message' => 'Net pay differs from the previous posted period by more than 10%.'];
        if (! $run->country_pack_id) $alerts[] = ['code' => 'NO_COUNTRY_PACK', 'message' => 'No reviewed statutory country pack is assigned; only configured compensation items were calculated.'];

        return [
            'business_id' => $run->business_id,
            'payroll_run_id' => $run->id,
            'employment_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'employee_number' => $profile->employee_number,
            'employment_snapshot' => ['name' => optional($profile->user)->user_full_name, 'job_title' => $profile->job_title, 'department_id' => $profile->department_id, 'location_id' => $profile->location_id, 'employment_type' => $profile->employment_type],
            'calculation_inputs' => ['compensation' => $compensation, 'period_start' => $run->period->starts_on->toDateString(), 'period_end' => $run->period->ends_on->toDateString(), 'formula_version' => $run->formula_version],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'statutory_results' => ['country_pack_id' => $run->country_pack_id, 'country_pack_version' => optional($run->countryPack)->version],
            'gross_amount' => round($gross, 4),
            'deduction_amount' => round($deductionTotal, 4),
            'net_amount' => $net,
            'prior_period_net' => $prior,
            'variance_percent' => $variance,
            'alerts' => $alerts,
            'status' => 'calculated',
            'version' => 1,
        ];
    }

    private function ruleAmount(float $gross, array $rule): float
    {
        $threshold = max(0, (float) ($rule['threshold'] ?? 0));
        if ($gross <= $threshold) return 0.0;
        $amount = ($rule['type'] ?? 'percent') === 'fixed'
            ? max(0, (float) ($rule['amount'] ?? 0))
            : max(0, $gross - $threshold) * max(0, (float) ($rule['rate'] ?? 0)) / 100;
        if (isset($rule['cap'])) $amount = min($amount, max(0, (float) $rule['cap']));

        return round($amount, 4);
    }

    private function baseCompensationForPeriod(PayrollRun $run, EmploymentProfile $profile, array $compensation): array
    {
        $amount = max(0, (float) ($compensation['amount'] ?? 0));
        $basis = (string) ($compensation['basis'] ?? $compensation['pay_period'] ?? 'month');
        $periodStart = $run->period->starts_on->copy()->startOfDay();
        $periodEnd = $run->period->ends_on->copy()->startOfDay();
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $activeStart = $profile->hire_date && $profile->hire_date->greaterThan($periodStart) ? $profile->hire_date->copy() : $periodStart;
        $activeEnd = $profile->termination_date && $profile->termination_date->lessThan($periodEnd) ? $profile->termination_date->copy() : $periodEnd;
        $activeDays = max(0, $activeStart->diffInDays($activeEnd) + 1);
        $activeFactor = $periodDays > 0 ? $activeDays / $periodDays : 0;
        $alerts = [];

        $periodAmount = match ($basis) {
            'hour' => $amount * max(0, (float) ($compensation['standard_hours_per_week'] ?? 40)) * ($periodDays / 7),
            'day' => $amount * $this->weekdaysBetween($periodStart, $periodEnd),
            'week' => $periodDays >= 6 && $periodDays <= 8 ? $amount : $amount * ($periodDays / 7),
            'year' => $periodDays >= 27 && $periodDays <= 31 ? $amount / 12 : $amount * ($periodDays / 365.2425),
            default => $periodDays >= 27 && $periodDays <= 31 ? $amount : $amount * 12 * ($periodDays / 365.2425),
        };
        if ($basis === 'hour') $alerts[] = ['code' => 'HOURLY_ESTIMATE', 'message' => 'Hourly base pay uses configured standard weekly hours; verify approved payable hours before approval.'];
        if ($activeFactor < 1) $alerts[] = ['code' => 'PRORATED_EMPLOYMENT', 'message' => 'Base compensation was prorated for a partial employment period.'];

        return [round($periodAmount * $activeFactor, 4), $alerts];
    }

    private function weekdaysBetween($start, $end): int
    {
        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) if (! $date->isWeekend()) $days++;

        return $days;
    }
}
