<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Restaurant\ResTable;
use App\Restaurant\WaiterRequest;
use App\Services\RestaurantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WaiterRequestController extends Controller
{
    public function index(RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $locationIds = $context->permittedLocationIds($businessId);
        $requests = WaiterRequest::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->whereIn('status', ['open', 'acknowledged'])
            ->oldest()->paginate(50);
        $locations = \App\BusinessLocation::forDropdown($businessId);
        $tables = ResTable::where('business_id', $businessId)
            ->when($locationIds !== null, fn ($q) => $q->whereIn('location_id', $locationIds))
            ->orderBy('name')->get(['id', 'location_id', 'name']);

        return view('restaurant.operations.waiter_requests', compact('requests', 'locations', 'tables'));
    }

    public function store(Request $request, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $data = $request->validate([
            'location_id' => ['required', 'integer'],
            'table_id' => ['required', 'integer'],
            'transaction_id' => ['nullable', 'integer'],
            'request_type' => ['required', Rule::in(array_keys(config('restaurant_operations.waiter_request_types', [])))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'assigned_to' => ['nullable', 'integer'],
        ]);
        $context->assertLocation($businessId, (int) $data['location_id'], $request->user());
        ResTable::where('business_id', $businessId)->where('location_id', $data['location_id'])->whereKey($data['table_id'])->firstOrFail();
        WaiterRequest::create($data + ['business_id' => $businessId, 'public_reference' => (string) Str::uuid(), 'status' => 'open']);

        return back()->with('status', ['success' => 1, 'msg' => 'Waiter request added to the location queue.']);
    }

    public function update(Request $request, int $waiterRequest, RestaurantContextService $context)
    {
        $this->authorizeManage();
        $businessId = $context->businessId();
        $data = $request->validate(['status' => ['required', Rule::in(['acknowledged', 'resolved'])]]);
        DB::transaction(function () use ($businessId, $waiterRequest, $data, $request, $context) {
            $model = WaiterRequest::where('business_id', $businessId)->whereKey($waiterRequest)->lockForUpdate()->firstOrFail();
            $context->assertLocation($businessId, (int) $model->location_id, $request->user());
            $actor = (int) $request->user()->id;
            if ($data['status'] === 'acknowledged' && $model->status === 'open') {
                $model->update(['status' => 'acknowledged', 'acknowledged_by' => $actor, 'acknowledged_at' => now()]);
            } elseif ($data['status'] === 'resolved' && in_array($model->status, ['open', 'acknowledged'], true)) {
                $model->update(['status' => 'resolved', 'resolved_by' => $actor, 'resolved_at' => now()]);
            }
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Waiter request updated.']);
    }

    private function authorizeManage(): void
    {
        if (! auth()->user()->can('restaurant.waiter_requests.manage') && ! auth()->user()->can('sell.update')) {
            abort(403, 'Unauthorized action.');
        }
    }
}
