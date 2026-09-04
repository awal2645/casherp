<?php

namespace App\Http\Controllers\Restaurant;

use App\CashRegister;
use App\Http\Controllers\Controller;
use App\Restaurant\RegisterMovement;
use App\Restaurant\RegisterReconciliation;
use App\Services\RegisterReconciliationService;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegisterControlController extends Controller
{
    public function index(RestaurantContextService $context, RegisterReconciliationService $service)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $registers = CashRegister::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->latest()->limit(100)->get();
        $reconciliations = RegisterReconciliation::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->with('counts')->latest()->limit(100)->get();
        $expectedCash = $registers->mapWithKeys(fn ($register) => [$register->id => $service->calculateExpectedCash($register)]);

        return view('restaurant.operations.registers', compact('registers', 'reconciliations', 'expectedCash'));
    }

    public function movement(Request $request, RestaurantContextService $context)
    {
        if (! auth()->user()->can('restaurant.register.manage')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $data = $request->validate([
            'cash_register_id' => ['required', 'integer'],
            'movement_type' => ['required', Rule::in(array_keys(config('restaurant_operations.register_movement_types', [])))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $register = CashRegister::where('business_id', $businessId)->whereKey($data['cash_register_id'])->firstOrFail();
        $context->assertLocation($businessId, (int) $register->location_id, $request->user());
        RegisterMovement::create($data + [
            'business_id' => $businessId,
            'location_id' => $register->location_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', ['success' => 1, 'msg' => 'Controlled cash movement recorded.']);
    }

    public function submit(Request $request, int $register, RestaurantContextService $context, RegisterReconciliationService $service)
    {
        if (! auth()->user()->can('restaurant.register.reconcile')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $cashRegister = CashRegister::where('business_id', $businessId)->whereKey($register)->firstOrFail();
        $context->assertLocation($businessId, (int) $cashRegister->location_id, $request->user());
        $data = $request->validate([
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.denomination' => ['required', 'numeric', 'gt:0'],
            'counts.*.quantity' => ['required', 'integer', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->submit($businessId, $register, $data['counts'], $data['variance_reason'] ?? null, (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Register count submitted for independent review.']);
    }

    public function review(Request $request, int $reconciliation, RestaurantContextService $context, RegisterReconciliationService $service)
    {
        if (! auth()->user()->can('restaurant.register.approve')) {
            abort(403, 'Unauthorized action.');
        }
        $businessId = $context->businessId();
        $model = RegisterReconciliation::where('business_id', $businessId)->whereKey($reconciliation)->firstOrFail();
        $context->assertLocation($businessId, (int) $model->location_id, $request->user());
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->review($businessId, $reconciliation, $data['decision'], $data['review_note'] ?? null, (int) $request->user()->id);

        return back()->with('status', ['success' => 1, 'msg' => 'Register reconciliation reviewed.']);
    }

    public function export(RestaurantContextService $context)
    {
        $this->authorizeView();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $rows = RegisterReconciliation::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Register', 'Location', 'Expected cash', 'Counted cash', 'Variance', 'Status', 'Submitted at', 'Reviewed at']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->cash_register_id, $row->location_id, $row->expected_cash, $row->counted_cash, $row->variance, $row->status, $row->submitted_at, $row->reviewed_at]);
            }
            fclose($handle);
        }, 'restaurant-register-reconciliation-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeView(): void
    {
        if (! auth()->user()->can('restaurant.register.view') && ! auth()->user()->can('view_cash_register')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
