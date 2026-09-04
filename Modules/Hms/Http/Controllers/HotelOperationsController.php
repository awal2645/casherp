<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Hms\Entities\HmsOperationalTask;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\OperationalTaskService;

class HotelOperationsController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_operations');
        $query = HmsOperationalTask::where('business_id', $businessId)->with(['property', 'room.type', 'booking', 'assignedTo']);
        if ($request->filled('task_type')) {
            $query->where('task_type', $request->input('task_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        $tasks = $query->latest('occurred_at')->paginate(30);
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $rooms = HmsRoom::whereHas('type', fn ($q) => $q->where('business_id', $businessId))->with('type')->orderBy('room_number')->get();
        $bookings = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')
            ->whereIn('hms_booking_status', ['reserved', 'checked_in'])->latest()->limit(200)->pluck('ref_no', 'id');
        $staff = User::forDropdown($businessId, false, false, false);
        $summary = HmsOperationalTask::where('business_id', $businessId)->selectRaw('task_type, status, COUNT(*) as total')
            ->groupBy('task_type', 'status')->get();

        return view('hms::operations.index', compact('tasks', 'properties', 'rooms', 'bookings', 'staff', 'summary'));
    }

    public function store(Request $request, OperationalTaskService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_operations');
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'hms_room_id' => 'nullable|integer',
            'transaction_id' => ['nullable', 'integer', Rule::exists('transactions', 'id')->where(fn ($q) => $q
                ->where('business_id', $businessId)
                ->where('hms_property_id', (int) $request->input('hms_property_id'))
                ->where('type', 'hms_booking'))],
            'task_type' => ['required', Rule::in(['maintenance', 'out_of_order', 'minibar', 'linen', 'laundry', 'turndown', 'guest_request', 'lost_found'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'item_name' => 'nullable|string|max:191',
            'quantity' => 'nullable|numeric|min:0',
            'charge_amount' => 'nullable|numeric|min:0',
            'due_at' => 'nullable|date',
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('business_id', $businessId)],
            'custody_reference' => 'nullable|string|max:80',
            'description' => 'required|string|max:5000',
        ]);
        if (! empty($data['hms_room_id'])) {
            HmsRoom::where('hms_property_id', $data['hms_property_id'])
                ->whereHas('type', fn ($q) => $q->where('business_id', $businessId))->findOrFail($data['hms_room_id']);
        }
        $service->create($data + ['business_id' => $businessId], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.operational_task_saved')]);
    }

    public function transition(Request $request, $task, OperationalTaskService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_operations');
        $data = $request->validate(['status' => ['required', Rule::in(['assigned', 'in_progress', 'resolved', 'cancelled'])], 'notes' => 'nullable|string|max:3000']);
        $service->transition($businessId, (int) $task, $data['status'], (int) auth()->id(), $data['notes'] ?? null);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.operational_task_updated')]);
    }
}
