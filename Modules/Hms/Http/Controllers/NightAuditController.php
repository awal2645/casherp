<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsCashierShift;
use Modules\Hms\Entities\HmsFolioEntry;
use Modules\Hms\Entities\HmsNightAudit;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\NightAuditService;

class NightAuditController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request, NightAuditService $service)
    {
        $businessId = $this->authorizeHms('hms.run_night_audit', 'hms_finance_operations');
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $propertyId = (int) ($request->input('property_id') ?: $properties->keys()->first());
        $preview = $propertyId ? $service->preview($businessId, $propertyId) : null;
        $audits = HmsNightAudit::where('business_id', $businessId)->with('property')->latest('closed_at')->limit(30)->get();
        $shifts = HmsCashierShift::where('business_id', $businessId)->with(['property', 'user'])->latest('opened_at')->limit(30)->get();

        return view('hms::night_audit.index', compact('properties', 'propertyId', 'preview', 'audits', 'shifts'));
    }

    public function close(Request $request, NightAuditService $service)
    {
        $businessId = $this->authorizeHms('hms.run_night_audit', 'hms_finance_operations');
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'override' => 'nullable|boolean',
            'override_reason' => 'nullable|string|max:2000',
        ]);
        if ($request->boolean('override')
            && ! (auth()->user()->can('superadmin') || auth()->user()->can('hms.override_night_audit'))) {
            abort(403, 'Unauthorized action.');
        }
        $service->close($businessId, (int) $data['property_id'], (int) auth()->id(), $request->boolean('override'), $data['override_reason'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.night_audit_closed')]);
    }

    public function openShift(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_cashier_shifts', 'hms_finance_operations');
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'opening_float' => 'required|numeric|min:0',
        ]);
        $property = HmsProperty::where('business_id', $businessId)->findOrFail($data['property_id']);
        $exists = HmsCashierShift::where('business_id', $businessId)->where('hms_property_id', $property->id)
            ->where('user_id', auth()->id())->where('status', 'open')->exists();
        if (! $exists) {
            HmsCashierShift::create([
                'business_id' => $businessId, 'hms_property_id' => $property->id, 'user_id' => auth()->id(),
                'business_date' => $property->business_date, 'opening_float' => $data['opening_float'],
                'expected_cash' => $data['opening_float'], 'status' => 'open', 'opened_at' => now(),
            ]);
        }

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.cashier_shift_opened')]);
    }

    public function closeShift(Request $request, $shift)
    {
        $businessId = $this->authorizeHms('hms.manage_cashier_shifts', 'hms_finance_operations');
        $model = HmsCashierShift::where('business_id', $businessId)->where('user_id', auth()->id())->where('status', 'open')->findOrFail($shift);
        $data = $request->validate(['declared_cash' => 'required|numeric|min:0', 'variance_reason' => 'nullable|string|max:1000']);
        $cash = HmsFolioEntry::where('business_id', $businessId)->where('payment_method', 'cash')
            ->where('created_by', $model->user_id)
            ->whereHas('folio', fn ($query) => $query
                ->where('business_id', $businessId)
                ->where('hms_property_id', $model->hms_property_id))
            ->where('direction', 'credit')->whereIn('status', ['posted', 'approved'])
            ->whereBetween('posted_at', [$model->opened_at, now()])->sum('base_amount');
        $expected = round((float) $model->opening_float + (float) $cash, 4);
        $variance = round((float) $data['declared_cash'] - $expected, 4);
        if (abs($variance) >= 0.0001 && empty($data['variance_reason'])) {
            return back()->withErrors(['variance_reason' => __('hms::lang.variance_reason_required')])->withInput();
        }
        $model->update(['expected_cash' => $expected, 'declared_cash' => $data['declared_cash'], 'variance' => $variance,
            'variance_reason' => $data['variance_reason'] ?? null, 'status' => 'closed', 'closed_at' => now()]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.cashier_shift_closed')]);
    }
}
