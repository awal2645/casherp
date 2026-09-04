<?php

namespace Modules\Essentials\Http\Controllers;

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\PayrollCountryPack;
use Modules\Essentials\Entities\PayrollPaymentBatch;
use Modules\Essentials\Entities\PayrollPeriod;
use Modules\Essentials\Entities\PayrollRun;
use Modules\Essentials\Entities\PayrollInput;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\HrmAuditService;
use Modules\Essentials\Services\PayrollRunWorkflowService;

class PayrollGovernanceController extends Controller
{
    use AuthorizesHrmRequests;

    protected ModuleUtil $moduleUtil;
    private PayrollRunWorkflowService $workflow;
    private HrmAuditService $audit;

    public function __construct(ModuleUtil $moduleUtil, PayrollRunWorkflowService $workflow, HrmAuditService $audit)
    {
        $this->moduleUtil = $moduleUtil;
        $this->workflow = $workflow;
        $this->audit = $audit;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, ['essentials.manage_payroll_runs', 'essentials.approve_payroll_runs']);
        $runs = PayrollRun::forBusiness($businessId)->with(['period', 'approvals.actor'])->latest()->paginate(25);
        $periods = PayrollPeriod::forBusiness($businessId)->latest('starts_on')->get();
        $countryPacks = PayrollCountryPack::forBusiness($businessId)->latest('effective_from')->get();
        $summary = [
            'draft' => PayrollRun::forBusiness($businessId)->whereIn('status', ['draft', 'calculated'])->count(),
            'awaiting_approval' => PayrollRun::forBusiness($businessId)->where('status', 'reviewed')->count(),
            'ready_to_pay' => PayrollRun::forBusiness($businessId)->whereIn('status', ['approved', 'posted'])->count(),
            'paid' => PayrollRun::forBusiness($businessId)->whereIn('status', ['paid', 'closed'])->count(),
        ];

        return view('essentials::payroll_governance.index', compact('runs', 'periods', 'countryPacks', 'summary'));
    }

    public function show(Request $request, int $run)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, ['essentials.manage_payroll_runs', 'essentials.approve_payroll_runs']);
        $record = PayrollRun::forBusiness($businessId)
            ->with(['period', 'countryPack', 'items.profile.user', 'inputs.profile.user', 'approvals.actor', 'paymentBatches.allocations'])
            ->findOrFail($run);
        $profiles = EmploymentProfile::forBusiness($businessId)->with('user:id,surname,first_name,last_name')->whereIn('employment_status', ['active', 'probation', 'confirmed', 'on_leave'])->orderBy('employee_number')->get();

        return view('essentials::payroll_governance.show', compact('record', 'profiles'));
    }

    public function storePeriod(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_payroll_runs');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('hrm_payroll_periods')->where('business_id', $businessId)],
            'name' => ['required', 'string', 'max:191'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'payment_date' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        $overlaps = PayrollPeriod::forBusiness($businessId)
            ->where(fn ($query) => $query->whereBetween('starts_on', [$data['starts_on'], $data['ends_on']])->orWhereBetween('ends_on', [$data['starts_on'], $data['ends_on']])->orWhere(fn ($q) => $q->where('starts_on', '<=', $data['starts_on'])->where('ends_on', '>=', $data['ends_on'])))
            ->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'Payroll periods must not overlap.']);
        }
        $period = PayrollPeriod::create($data + ['business_id' => $businessId, 'status' => 'open']);
        $this->audit->record($businessId, 'payroll.period_created', PayrollPeriod::class, $period->id, [], $period->toArray());

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll period created.']);
    }

    public function storeCountryPack(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_payroll_runs');
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'jurisdiction' => ['nullable', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'version' => ['required', 'string', 'max:40'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'rules_json' => ['required', 'json', 'max:50000'],
            'professionally_reviewed' => ['nullable', 'boolean'],
            'reviewed_by' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $reviewed = $request->boolean('professionally_reviewed');
        $active = $request->boolean('is_active');
        if ($active && (! $reviewed || empty($data['reviewed_by']))) {
            throw ValidationException::withMessages(['is_active' => 'A country pack must be professionally reviewed and identify its reviewer before activation.']);
        }
        $rules = json_decode($data['rules_json'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($rules)) {
            throw ValidationException::withMessages(['rules_json' => 'Country pack rules must be a JSON object.']);
        }
        $deductionRules = (array) ($rules['employee_deductions'] ?? []);
        if (count($deductionRules) > 50) throw ValidationException::withMessages(['rules_json' => 'A country pack can contain at most 50 employee deduction rules.']);
        foreach ($deductionRules as $index => $rule) {
            if (! is_array($rule) || ! in_array($rule['type'] ?? 'percent', ['percent', 'fixed'], true)) throw ValidationException::withMessages(['rules_json' => "Deduction rule {$index} has an unsupported type."]);
            if (($rule['type'] ?? 'percent') === 'percent' && (! is_numeric($rule['rate'] ?? null) || (float) $rule['rate'] < 0 || (float) $rule['rate'] > 100)) throw ValidationException::withMessages(['rules_json' => "Deduction rule {$index} needs a rate between 0 and 100."]);
            if (($rule['type'] ?? 'percent') === 'fixed' && (! is_numeric($rule['amount'] ?? null) || (float) $rule['amount'] < 0)) throw ValidationException::withMessages(['rules_json' => "Deduction rule {$index} needs a non-negative fixed amount."]);
            foreach (['threshold', 'cap'] as $amountField) if (isset($rule[$amountField]) && (! is_numeric($rule[$amountField]) || (float) $rule[$amountField] < 0)) throw ValidationException::withMessages(['rules_json' => "Deduction rule {$index} has an invalid {$amountField}."]);
        }
        $jurisdiction = $data['jurisdiction'] ?? '';
        $duplicate = PayrollCountryPack::forBusiness($businessId)
            ->where('country_code', strtoupper($data['country_code']))
            ->where('jurisdiction', $jurisdiction)
            ->where('version', $data['version'])
            ->exists();
        if ($duplicate) throw ValidationException::withMessages(['version' => 'That country, jurisdiction and version already exists.']);
        $pack = DB::transaction(function () use ($businessId, $data, $rules, $reviewed, $active) {
            if ($active) PayrollCountryPack::forBusiness($businessId)
                ->where('country_code', strtoupper($data['country_code']))
                ->where('jurisdiction', $data['jurisdiction'] ?? '')
                ->update(['is_active' => false]);

            return PayrollCountryPack::create([
                'business_id' => $businessId,
                'country_code' => strtoupper($data['country_code']),
                'jurisdiction' => $data['jurisdiction'] ?? '',
                'name' => $data['name'],
                'version' => $data['version'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'rules' => $rules,
                'professionally_reviewed' => $reviewed,
                'reviewed_by' => $reviewed ? $data['reviewed_by'] : null,
                'reviewed_at' => $reviewed ? now() : null,
                'is_active' => $active,
                'created_by' => auth()->id(),
            ]);
        });
        $this->audit->record($businessId, 'payroll.country_pack_created', PayrollCountryPack::class, $pack->id, [], $pack->toArray(), $reviewed ? 'Reviewed rules added' : 'Draft rules added');

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll country pack saved.']);
    }

    public function storeRun(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_payroll_runs');
        $data = $request->validate([
            'payroll_period_id' => ['required', Rule::exists('hrm_payroll_periods', 'id')->where('business_id', $businessId)],
            'country_pack_id' => ['nullable', Rule::exists('hrm_payroll_country_packs', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('is_active', true)->where('professionally_reviewed', true))],
            'name' => ['required', 'string', 'max:191'],
            'run_type' => ['required', Rule::in(['regular', 'off_cycle', 'final', 'correction'])],
            'location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $period = PayrollPeriod::forBusiness($businessId)->findOrFail($data['payroll_period_id']);
        if ($period->status !== 'open') throw ValidationException::withMessages(['payroll_period_id' => 'The selected period is not open.']);
        $business = Business::with('currency')->findOrFail($businessId);
        $currency = optional($business->currency)->code ?: 'USD';
        $run = PayrollRun::create($data + [
            'uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'draft', 'currency_code' => strtoupper($currency),
            'exchange_rate' => 1, 'formula_version' => 'core-1', 'created_by' => auth()->id(), 'updated_by' => auth()->id(),
        ]);
        $this->audit->record($businessId, 'payroll.run_created', PayrollRun::class, $run->id, [], $run->toArray());

        return redirect()->route('hrm.payroll-runs.show', $run->id)->with('status', ['success' => 1, 'msg' => 'Payroll run created.']);
    }

    public function calculate(Request $request, int $run)
    {
        $record = $this->run($request, $run, 'essentials.manage_payroll_runs');
        $this->workflow->calculate($record, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll calculated on the server. Review all alerts and variances.']);
    }

    public function storeInput(Request $request, int $run)
    {
        $record = $this->run($request, $run, 'essentials.manage_payroll_runs');
        $data = $request->validate([
            'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $record->business_id)],
            'input_type' => ['required', Rule::in(['earning', 'deduction'])], 'code' => ['required', 'alpha_dash', 'max:40'], 'label' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'], 'rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'amount' => ['nullable', 'required_without:rate', 'numeric', 'min:0', 'max:999999999999'], 'source' => ['required', Rule::in(['manual', 'timesheet', 'benefit', 'expense', 'correction'])],
        ]);
        if (! in_array($record->status, ['draft', 'calculated'], true)) throw ValidationException::withMessages(['payroll_run' => 'Inputs can only be changed before payroll review.']);
        $amount = isset($data['rate']) ? round((float) $data['quantity'] * (float) $data['rate'], 4) : round((float) $data['amount'], 4);
        $data['amount'] = $amount;
        $data['code'] = strtoupper($data['code']);
        $input = DB::transaction(function () use ($record, $data, $amount) {
            $locked = PayrollRun::forBusiness($record->business_id)->lockForUpdate()->findOrFail($record->id);
            if (! in_array($locked->status, ['draft', 'calculated'], true)) throw ValidationException::withMessages(['payroll_run' => 'Inputs can only be changed before payroll review.']);
            if ($locked->status === 'calculated') {
                $locked->items()->delete();
                $locked->update(['status' => 'draft', 'calculated_at' => null, 'gross_total' => 0, 'deduction_total' => 0, 'net_total' => 0, 'employee_count' => 0]);
            }

            return PayrollInput::create([
                'uuid' => (string) Str::uuid(), 'business_id' => $locked->business_id, 'payroll_run_id' => $locked->id, 'employment_profile_id' => $data['employment_profile_id'],
                'input_type' => $data['input_type'], 'code' => $data['code'], 'label' => $data['label'], 'quantity' => $data['quantity'], 'rate' => $data['rate'] ?? null,
                'amount' => $data['amount'], 'source' => $data['source'], 'metadata' => [], 'status' => 'pending', 'created_by' => auth()->id(),
            ]);
        });
        $this->audit->record($record->business_id, 'payroll.input_created', PayrollInput::class, $input->id, [], $input->toArray());

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll input added for independent approval.']);
    }

    public function decideInput(Request $request, int $input)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.approve_payroll_runs');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'reason' => ['nullable', 'string', 'max:2000']]);
        if ($data['decision'] === 'rejected' && empty($data['reason'])) throw ValidationException::withMessages(['reason' => 'A rejection reason is required.']);
        $record = DB::transaction(function () use ($businessId, $input, $data) {
            $record = PayrollInput::forBusiness($businessId)->with('run')->lockForUpdate()->findOrFail($input);
            if ($record->status !== 'pending' || ! in_array($record->run->status, ['draft', 'calculated'], true)) throw ValidationException::withMessages(['decision' => 'This payroll input can no longer be decided.']);
            if ((int) $record->created_by === (int) auth()->id()) throw ValidationException::withMessages(['decision' => 'Separation of duties requires a different user to approve or reject this input.']);
            $metadata = (array) $record->metadata;
            if (! empty($data['reason'])) $metadata['decision_reason'] = $data['reason'];
            $record->update(['status' => $data['decision'], 'metadata' => $metadata, 'approved_by' => auth()->id(), 'approved_at' => now()]);

            return $record;
        });
        $this->audit->record($businessId, 'payroll.input_'.$data['decision'], PayrollInput::class, $record->id, ['status' => 'pending'], ['status' => $data['decision']], $data['reason'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll input decision recorded.']);
    }

    public function transition(Request $request, int $run)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['review', 'approve', 'post', 'close', 'reject'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        $permission = in_array($data['action'], ['approve', 'post', 'close', 'reject'], true) ? 'essentials.approve_payroll_runs' : 'essentials.manage_payroll_runs';
        $record = $this->run($request, $run, $permission);
        $this->workflow->transition($record, $data['action'], (int) auth()->id(), $data['comment'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => 'Payroll workflow updated.']);
    }

    public function storePaymentBatch(Request $request, int $run)
    {
        $record = $this->run($request, $run, 'essentials.pay_payroll');
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', Rule::unique('hrm_payroll_payment_batches')->where('business_id', $record->business_id)],
            'payment_method' => ['required', Rule::in(['bank_transfer', 'cash', 'cheque', 'mobile_money', 'other'])],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('business_id', $record->business_id)->where('is_closed', false)->whereNull('deleted_at'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->workflow->preparePaymentBatch($record, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Payment batch prepared.']);
    }

    public function reconcilePaymentBatch(Request $request, int $batch)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.pay_payroll');
        $record = PayrollPaymentBatch::forBusiness($businessId)->findOrFail($batch);
        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'reconciled', 'failed'])],
            'provider_reference' => ['nullable', 'required_if:status,reconciled', 'string', 'max:191'],
            'note' => ['nullable', 'required_if:status,failed', 'string', 'max:2000'],
        ]);
        $this->workflow->reconcilePaymentBatch($record, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Payment reconciliation recorded.']);
    }

    private function run(Request $request, int $id, string $permission): PayrollRun
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, $permission);

        return PayrollRun::forBusiness($businessId)->findOrFail($id);
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }
}
