<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsFolio;
use Modules\Hms\Entities\HmsFolioEntry;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\FolioService;
use App\SecurityDeposit;
use App\SecurityDepositEntry;
use App\Services\SecurityDepositService;
use App\Services\TenantPaymentMethodService;

class FolioController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(SecurityDepositService $depositService)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $folios = HmsFolio::where('business_id', $businessId)->with(['property', 'contact', 'groupBooking', 'entries'])->latest()->paginate(30);

        $securityAlerts = $depositService->alertsForBusiness($businessId, ['hms_booking', 'hms_event'])
            ->map(function ($alert) use ($businessId) {
                $alert['folio_id'] = HmsFolio::where('business_id', $businessId)
                    ->when($alert['deposit']->context_type === 'hms_booking', fn ($query) => $query->where('transaction_id', $alert['deposit']->context_id))
                    ->when($alert['deposit']->context_type === 'hms_event', fn ($query) => $query->where('hms_event_booking_id', $alert['deposit']->context_id))
                    ->value('id');

                return $alert;
            });

        return view('hms::folios.index', compact('folios', 'securityAlerts'));
    }

    public function show($folio, SecurityDepositService $depositService, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->with(['property', 'contact', 'booking', 'eventBooking', 'groupBooking', 'entries', 'deposits'])->findOrFail($folio);
        $transferTargets = HmsFolio::where('business_id', $businessId)
            ->where('id', '!=', $model->id)
            ->where('currency_id', $model->currency_id)
            ->whereIn('status', ['open', 'settled'])
            ->with(['property', 'contact', 'groupBooking'])
            ->get()
            ->mapWithKeys(fn ($target) => [$target->id => $target->folio_number . ' — '
                . (optional($target->contact)->name ?: optional($target->groupBooking)->name ?: __('hms::lang.house_folio'))
                . ' (' . optional($target->property)->name . ')']);

        $securityDeposit = $depositService->find($businessId, 'hms_booking', (int) $model->transaction_id);
        $paymentMethods = $methods->settlementOptions($businessId);

        return view('hms::folios.show', compact('model', 'transferTargets', 'securityDeposit', 'paymentMethods'));
    }

    public function postEntry(Request $request, $folio, FolioService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->findOrFail($folio);
        $data = $request->validate([
            'entry_type' => ['required', Rule::in(['charge', 'payment', 'payment_deposit', 'deposit', 'refund', 'adjustment'])],
            'category' => ['required', Rule::in(['room_charge', 'restaurant', 'minibar', 'laundry', 'tax', 'fee', 'payment', 'booking_payment', 'deposit', 'refund', 'adjustment', 'other'])],
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => 'required|numeric|gt:0|max:999999999999',
            'tax_amount' => 'nullable|numeric|min:0|lte:amount',
            'exchange_rate' => 'nullable|numeric|gt:0',
            'payment_method' => 'nullable|string|max:40',
            'description' => 'required|string|max:1000',
        ]);
        $directionByType = [
            'charge' => 'debit',
            'payment' => 'credit',
            'deposit' => 'credit',
            'payment_deposit' => 'credit',
            'refund' => 'debit',
        ];
        if (isset($directionByType[$data['entry_type']])) {
            $data['direction'] = $directionByType[$data['entry_type']];
        }
        if (in_array($data['entry_type'], ['payment', 'deposit', 'payment_deposit', 'refund'], true)) {
            $data['category'] = $data['entry_type'] === 'payment_deposit' ? 'booking_payment' : $data['entry_type'];
            if (empty($data['payment_method'])) {
                throw ValidationException::withMessages(['payment_method' => 'Choose the actual method used to receive or return money.']);
            }
            $methods->assertSettlementMethod($businessId, $data['payment_method']);
        }
        if ($data['entry_type'] === 'charge'
            && ! in_array($data['category'], ['room_charge', 'restaurant', 'minibar', 'laundry', 'tax', 'fee', 'other'], true)) {
            throw ValidationException::withMessages([
                'category' => __('hms::lang.invalid_folio_charge_category'),
            ]);
        }
        $service->post($model, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.folio_entry_posted')]);
    }

    public function approveRefund($entry, FolioService $service)
    {
        $businessId = $this->authorizeHms('hms.approve_refunds', 'hms_finance_operations');
        $service->approveRefund($businessId, (int) $entry, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.refund_approved')]);
    }

    public function voidEntry(Request $request, $entry, FolioService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $data = $request->validate(['reason' => 'required|string|min:3|max:1000']);
        $service->void($businessId, (int) $entry, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.folio_entry_voided')]);
    }

    public function transferEntry(Request $request, $entry, FolioService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $data = $request->validate([
            'target_folio_id' => ['required', 'integer', Rule::exists('hms_folios', 'id')->where('business_id', $businessId)],
            'amount' => 'required|numeric|gt:0|max:999999999999',
            'idempotency_token' => 'required|uuid',
        ]);
        $service->transfer(
            $businessId,
            (int) $entry,
            (int) $data['target_folio_id'],
            (float) $data['amount'],
            $data['idempotency_token'],
            (int) auth()->id()
        );

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.folio_entry_transferred')]);
    }

    public function scheduleDeposit(Request $request, $folio, FolioService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_folios', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->findOrFail($folio);
        $data = $request->validate(['amount' => 'required|numeric|gt:0', 'due_date' => 'required|date', 'notes' => 'nullable|string|max:1000']);
        $service->scheduleDeposit($model, (float) $data['amount'], $data['due_date'], (int) auth()->id(), $data['notes'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.deposit_scheduled')]);
    }

    public function updateSecurityDeposit(Request $request, $folio, SecurityDepositService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_security_deposits', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->with(['booking', 'property', 'contact'])->findOrFail($folio);
        $data = $request->validate(['required_amount' => ['required', 'numeric', 'min:0'], 'due_date' => ['nullable', 'date']]);
        $deposit = $service->forHmsFolio($model, (float) $data['required_amount'], (int) auth()->id());
        if ($deposit) {
            $deposit->update(['due_date' => $data['due_date'] ?? $deposit->due_date, 'updated_by' => auth()->id()]);
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Refundable security-deposit requirement updated outside the guest folio and accounting totals.']);
    }

    public function receiptSecurityDeposit(Request $request, $folio, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeHms('hms.manage_security_deposits', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->with(['booking', 'property', 'contact'])->findOrFail($folio);
        $deposit = $service->forHmsFolio($model);
        abort_unless($deposit, 422, 'Configure the refundable security deposit first.');
        $data = $this->securityMoneyInput($request);
        $methods->assertSettlementMethod($businessId, $data['payment_method']);
        $service->receipt($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security deposit received into the separate refundable register; the folio balance was not changed.']);
    }

    public function damageSecurityDeposit(
        Request $request,
        $folio,
        SecurityDepositService $service,
        FolioService $folios
    )
    {
        $businessId = $this->authorizeHms('hms.manage_security_deposits', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->with(['booking', 'property', 'contact'])->findOrFail($folio);
        $deposit = $service->forHmsFolio($model);
        abort_unless($deposit, 422, 'Configure the refundable security deposit first.');
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'occurred_on' => ['required', 'date'], 'description' => ['required', 'string', 'min:5', 'max:3000'], 'reference' => ['nullable', 'string', 'max:191'], 'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('security-deposit-evidence/'.$businessId, 'public');
        }
        $availableBeforeAssessment = $deposit->available_refund;
        DB::transaction(function () use ($service, $folios, $deposit, $model, $data, $availableBeforeAssessment) {
            $damage = $service->damage($deposit, $data, (int) auth()->id());
            $charge = $folios->post($model, [
                'entry_type' => 'charge',
                'category' => 'fee',
                'direction' => 'debit',
                'amount' => $damage->amount,
                'description' => 'Damage/loss charge: '.$damage->description,
                'idempotency_key' => 'security-damage-charge:'.$damage->id,
                'metadata' => ['security_deposit_entry_id' => $damage->id, 'evidence_path' => $damage->evidence_path],
            ], (int) auth()->id());
            $damage->update(['hms_folio_entry_id' => $charge->id]);

            $retained = min((float) $damage->amount, (float) $availableBeforeAssessment);
            if ($retained > 0.0001) {
                $folios->post($model, [
                    'entry_type' => 'payment',
                    'category' => 'security_deposit_applied',
                    'direction' => 'credit',
                    'amount' => $retained,
                    'payment_method' => 'security_deposit_applied',
                    'description' => 'Refundable security deposit retained against damage assessment #'.$damage->id,
                    'idempotency_key' => 'security-damage-application:'.$damage->id,
                    'metadata' => ['security_deposit_entry_id' => $damage->id],
                ], (int) auth()->id());
            }
        }, 3);

        return back()->with('status', ['success' => 1, 'msg' => 'Hotel damage charge added to the guest folio. The retained security amount was applied to that charge only; any excess remains due.']);
    }

    public function refundSecurityDeposit(Request $request, $folio, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $businessId = $this->authorizeHms('hms.manage_security_deposits', 'hms_finance_operations');
        $model = HmsFolio::where('business_id', $businessId)->with(['booking', 'property', 'contact'])->findOrFail($folio);
        $deposit = $service->forHmsFolio($model);
        abort_unless($deposit, 422);
        $data = $this->securityMoneyInput($request);
        $methods->assertSettlementMethod($businessId, $data['payment_method']);
        $service->requestRefund($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security-deposit refund submitted for approval.']);
    }

    public function approveSecurityDepositRefund($entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeHms('hms.approve_security_deposit_refunds', 'hms_finance_operations');
        $model = SecurityDepositEntry::where('business_id', $businessId)->where('entry_type', 'refund')->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->whereIn('context_type', ['hms_booking', 'hms_event'])->findOrFail($model->security_deposit_id);
        $this->assertDepositFolio($businessId, $deposit);
        $service->approveRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Hotel security-deposit refund approved. It remains uncleared until payment is confirmed.']);
    }

    public function paySecurityDepositRefund($entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeHms('hms.approve_security_deposit_refunds', 'hms_finance_operations');
        $model = SecurityDepositEntry::where('business_id', $businessId)->where('entry_type', 'refund')->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->whereIn('context_type', ['hms_booking', 'hms_event'])->findOrFail($model->security_deposit_id);
        $this->assertDepositFolio($businessId, $deposit);
        $service->payRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Hotel security-deposit refund payment confirmed and the held balance updated.']);
    }

    public function voidSecurityDepositEntry(Request $request, $entry, SecurityDepositService $service)
    {
        $businessId = $this->authorizeHms('hms.approve_security_deposit_refunds', 'hms_finance_operations');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $model = SecurityDepositEntry::where('business_id', $businessId)->findOrFail($entry);
        $deposit = SecurityDeposit::forBusiness($businessId)->whereIn('context_type', ['hms_booking', 'hms_event'])->findOrFail($model->security_deposit_id);
        $this->assertDepositFolio($businessId, $deposit);
        $service->void($model, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Security-deposit entry voided with an audit reason.']);
    }

    public function waiveSecurityDeposit(Request $request, $folio, SecurityDepositService $service)
    {
        $businessId = $this->authorizeHms('hms.approve_security_deposit_refunds', 'hms_finance_operations');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $model = HmsFolio::where('business_id', $businessId)->with(['booking', 'property', 'contact'])->findOrFail($folio);
        $deposit = $service->forHmsFolio($model);
        abort_unless($deposit, 422);
        $service->waiveUncollected($deposit, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Uncollected hotel security-deposit requirement waived with an audit reason.']);
    }

    private function securityMoneyInput(Request $request): array
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

    private function assertDepositFolio(int $businessId, SecurityDeposit $deposit): HmsFolio
    {
        return HmsFolio::where('business_id', $businessId)
            ->when($deposit->context_type === 'hms_booking', fn ($query) => $query->where('transaction_id', $deposit->context_id))
            ->when($deposit->context_type === 'hms_event', fn ($query) => $query->where('hms_event_booking_id', $deposit->context_id))
            ->firstOrFail();
    }
}
