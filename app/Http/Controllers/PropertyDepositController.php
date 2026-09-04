<?php

namespace App\Http\Controllers;

use App\Property;
use App\PropertyLease;
use App\SecurityDeposit;
use App\SecurityDepositEntry;
use App\Services\DamageChargeDocumentService;
use App\Services\PropertyAccessService;
use App\Services\PropertyPaymentDepositService;
use App\Services\SecurityDepositService;
use App\Services\TenantPaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\AccountingAccount;

class PropertyDepositController extends Controller
{
    public function index(SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.manage', 'property.deposit.refund', 'property.view']);
        $leaseIds = $this->permittedLeaseIds($businessId, 'view');
        $deposits = SecurityDeposit::forBusiness($businessId)->where('context_type', 'property_lease')
            ->whereIn('context_id', $leaseIds)->with(['entries', 'contact', 'location'])->latest()->paginate(30);
        $alerts = $service->alertsForBusiness($businessId, ['property_lease'])
            ->filter(fn ($alert) => in_array((int) $alert['deposit']->context_id, $leaseIds->all(), true));

        return view('property.deposits.index', compact('deposits', 'alerts'));
    }

    public function show($lease, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeAny(['property.deposit.manage', 'property.deposit.refund', 'property.view']);
        $model = $this->lease($businessId, (int) $lease, 'view');
        $securityDeposit = $service->forPropertyLease($model);
        $model->load(['paymentDeposits.allocations.rentDue']);
        $paymentMethods = $methods->settlementOptions($businessId);
        $accounts = AccountingAccount::where('business_id', $businessId)->where('status', 'active')
            ->where('account_primary_type', 'asset')->pluck('name', 'id');

        return view('property.deposits.show', compact('model', 'securityDeposit', 'paymentMethods', 'accounts'));
    }

    public function updateRequirement(Request $request, $lease, SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.manage']);
        $model = $this->lease($businessId, (int) $lease, 'rent');
        $data = $request->validate([
            'required_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'due_date' => ['nullable', 'date'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ]);
        $deposit = $service->forPropertyLease($model, (float) $data['required_amount'], (int) auth()->id());
        if ($deposit) {
            $deposit->update(['due_date' => $data['due_date'] ?? null, 'terms' => $data['terms'] ?? $deposit->terms, 'updated_by' => auth()->id()]);
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Refundable security-deposit requirement updated without posting to accounting.']);
    }

    public function receipt(Request $request, $lease, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeAny(['property.deposit.manage']);
        $model = $this->lease($businessId, (int) $lease, 'rent');
        $deposit = $service->forPropertyLease($model);
        abort_unless($deposit, 422, 'Configure the refundable security deposit first.');
        $data = $this->moneyInput($request);
        $methods->assertSettlementMethod($businessId, $data['payment_method']);
        $service->receipt($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security deposit received into the separate refundable-deposit register. No income or invoice payment was posted.']);
    }

    public function damage(
        Request $request,
        $lease,
        SecurityDepositService $service,
        DamageChargeDocumentService $documents
    )
    {
        $businessId = $this->authorizeAny(['property.deposit.manage']);
        $model = $this->lease($businessId, (int) $lease, 'rent');
        $deposit = $service->forPropertyLease($model);
        abort_unless($deposit, 422, 'Configure the refundable security deposit first.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'min:5', 'max:3000'],
            'reference' => ['nullable', 'string', 'max:191'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('security-deposit-evidence/'.$businessId, 'public');
        }
        $damageDocument = DB::transaction(function () use ($service, $documents, $deposit, $model, $data) {
            $entry = $service->damage($deposit, $data, (int) auth()->id());

            return $documents->forPropertyLease($model, $entry, (int) auth()->id());
        }, 3);

        return back()->with('status', [
            'success' => 1,
            'msg' => 'Damage assessment recorded separately from revenue. Linked draft Damage Charge Invoice '.$damageDocument->document_number.' is ready for independent review and issue.',
        ]);
    }

    public function requestRefund(Request $request, $lease, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeAny(['property.deposit.manage']);
        $model = $this->lease($businessId, (int) $lease, 'rent');
        $deposit = $service->forPropertyLease($model);
        abort_unless($deposit, 422);
        $data = $this->moneyInput($request);
        $methods->assertSettlementMethod($businessId, $data['payment_method']);
        $service->requestRefund($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Refund submitted for independent approval.']);
    }

    public function approveRefund($entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.refund']);
        $model = SecurityDepositEntry::where('business_id', $businessId)->where('entry_type', 'refund')->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->where('context_type', 'property_lease')->findOrFail($model->security_deposit_id);
        $this->lease($businessId, (int) $deposit->context_id, 'accounting');
        $service->approveRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security-deposit refund approved. It remains uncleared until an authorized user confirms payment.']);
    }

    public function payRefund($entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.refund']);
        $model = SecurityDepositEntry::where('business_id', $businessId)->where('entry_type', 'refund')->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->where('context_type', 'property_lease')->findOrFail($model->security_deposit_id);
        $this->lease($businessId, (int) $deposit->context_id, 'accounting');
        $service->payRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security-deposit refund payment confirmed and the refundable balance updated.']);
    }

    public function voidEntry(Request $request, $entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.refund']);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $model = SecurityDepositEntry::where('business_id', $businessId)->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->where('context_type', 'property_lease')->findOrFail($model->security_deposit_id);
        $this->lease($businessId, (int) $deposit->context_id, 'accounting');
        $service->void($model, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Deposit-register entry voided with an audit reason.']);
    }

    public function waive(Request $request, $lease, SecurityDepositService $service)
    {
        $businessId = $this->authorizeAny(['property.deposit.refund']);
        $model = $this->lease($businessId, (int) $lease, 'accounting');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $deposit = $service->forPropertyLease($model);
        abort_unless($deposit, 422);
        $service->waiveUncollected($deposit, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Uncollected security-deposit requirement waived with an audit reason.']);
    }

    public function paymentDeposit(Request $request, $lease, PropertyPaymentDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeAny(['property.rent.manage']);
        $model = $this->lease($businessId, (int) $lease, 'rent');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'received_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'receipt_account_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $methods->assertSettlementMethod($businessId, $data['payment_method']);
        if (! empty($data['receipt_account_id'])) {
            abort_unless(AccountingAccount::where('business_id', $businessId)->where('account_primary_type', 'asset')->whereKey($data['receipt_account_id'])->exists(), 422, 'Invalid company receipt account.');
        }
        $service->record($model, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Payment Deposit (Advance) recorded and applied to the earliest rent balance where available.']);
    }

    private function moneyInput(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ]);
    }

    private function authorizeAny(array $permissions): int
    {
        $businessId = (int) session('user.business_id');
        $admin = auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.$businessId);
        abort_unless($admin || collect($permissions)->contains(fn ($permission) => auth()->user()->canForBusiness($permission, $businessId)), 403);

        return $businessId;
    }

    private function permittedLeaseIds(int $businessId, string $ability)
    {
        $propertyIds = app(PropertyAccessService::class)->scopeProperties(Property::query(), $businessId, auth()->user(), $ability)->pluck('id');

        return PropertyLease::where('business_id', $businessId)->whereHas('unit', fn ($query) => $query->whereIn('property_id', $propertyIds))->pluck('id');
    }

    private function lease(int $businessId, int $leaseId, string $ability): PropertyLease
    {
        $lease = PropertyLease::where('business_id', $businessId)->with(['unit.property.businessLocation', 'tenant'])->findOrFail($leaseId);
        app(PropertyAccessService::class)->assertAccess($lease->unit->property, auth()->user(), $businessId, $ability);

        return $lease;
    }
}
