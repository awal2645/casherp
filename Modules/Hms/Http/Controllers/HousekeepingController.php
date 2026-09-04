<?php

namespace Modules\Hms\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsHousekeepingTask;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Services\HousekeepingService;

class HousekeepingController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId();
        $this->authorizeView($businessId);
        $filters = $request->validate([
            'status' => 'nullable|in:pending,in_progress,cleaned,ready',
            'assigned_to' => 'nullable|integer',
            'scheduled_date' => 'nullable|date',
        ]);

        $tasks = HmsHousekeepingTask::where('business_id', $businessId)
            ->with(['room.type', 'assignedTo', 'booking.contact'])
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(! empty($filters['assigned_to']), function ($query) use ($filters) {
                $query->where('assigned_to', $filters['assigned_to']);
            })
            ->when(! empty($filters['scheduled_date']), function ($query) use ($filters) {
                $query->whereDate('scheduled_for', $filters['scheduled_date']);
            })
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'cleaned' THEN 3 ELSE 4 END")
            ->orderBy('scheduled_for')
            ->latest('id')
            ->paginate(25)
            ->appends($filters);

        $rooms = HmsRoom::whereHas('type', function ($query) use ($businessId) {
            $query->where('business_id', $businessId);
        })->with('type')->orderBy('room_number')->get();

        return view('hms::housekeeping.index', [
            'tasks' => $tasks,
            'rooms' => $rooms,
            'staff' => User::forDropdown($businessId, true),
            'summary' => [
                'dirty' => $rooms->where('housekeeping_status', 'dirty')->count(),
                'cleaning' => $rooms->where('housekeeping_status', 'cleaning')->count(),
                'cleaned' => $rooms->where('housekeeping_status', 'cleaned')->count(),
                'ready' => $rooms->where('housekeeping_status', 'ready')->count(),
                'overdue' => HmsHousekeepingTask::where('business_id', $businessId)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->where('scheduled_for', '<', now())
                    ->count(),
            ],
        ]);
    }

    public function create()
    {
        $businessId = $this->businessId();
        $this->authorizeManage($businessId);

        return view('hms::housekeeping.create', [
            'rooms' => HmsRoom::whereHas('type', function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })->with('type')->orderBy('room_number')->get(),
            'staff' => User::forDropdown($businessId, true),
            'taskTypes' => $this->taskTypes(),
        ]);
    }

    public function store(Request $request, HousekeepingService $service)
    {
        $businessId = $this->businessId();
        $this->authorizeManage($businessId);
        $data = $request->validate([
            'hms_room_id' => 'required|integer',
            'assigned_to' => 'nullable|integer',
            'task_type' => 'required|in:checkout_cleaning,stayover_cleaning,deep_cleaning,inspection',
            'priority' => 'required|in:low,normal,high,urgent',
            'scheduled_for' => 'required|date',
            'notes' => 'nullable|string|max:3000',
        ]);

        if (! empty($data['assigned_to'])) {
            $this->ensureUserBelongsToBusiness($businessId, (int) $data['assigned_to']);
        }

        $task = $service->createTask(
            $businessId,
            (int) $data['hms_room_id'],
            $data,
            (int) auth()->id()
        );

        return redirect()->route('hms.housekeeping.show', $task)->with(
            'status',
            ['success' => 1, 'msg' => 'Housekeeping task created.']
        );
    }

    public function show(HmsHousekeepingTask $task)
    {
        $businessId = $this->businessId();
        $this->authorizeView($businessId);
        $this->ensureTaskBusiness($task, $businessId);

        return view('hms::housekeeping.show', [
            'task' => $task->load([
                'room.type',
                'assignedTo',
                'creator',
                'completedBy',
                'inspectedBy',
                'booking.contact',
            ]),
            'staff' => User::forDropdown($businessId, true),
            'canManage' => $this->canManage(),
            'canPerform' => $this->canPerformTask($task),
            'canInspect' => $this->canInspect(),
        ]);
    }

    public function assign(
        Request $request,
        HmsHousekeepingTask $task,
        HousekeepingService $service
    ) {
        $businessId = $this->businessId();
        $this->authorizeManage($businessId);
        $this->ensureTaskBusiness($task, $businessId);
        $data = $request->validate(['assigned_to' => 'nullable|integer']);

        if (! empty($data['assigned_to'])) {
            $this->ensureUserBelongsToBusiness($businessId, (int) $data['assigned_to']);
        }
        $service->assign($task, $data['assigned_to'] ?? null);

        return back()->with(
            'status',
            ['success' => 1, 'msg' => 'Housekeeping assignment updated.']
        );
    }

    public function start(
        HmsHousekeepingTask $task,
        HousekeepingService $service
    ) {
        $businessId = $this->businessId();
        $this->authorizePerform($businessId, $task);
        $service->start($task, (int) auth()->id());

        return back()->with(
            'status',
            ['success' => 1, 'msg' => 'Housekeeping task started.']
        );
    }

    public function markCleaned(
        Request $request,
        HmsHousekeepingTask $task,
        HousekeepingService $service
    ) {
        $businessId = $this->businessId();
        $this->authorizePerform($businessId, $task);
        $data = $request->validate([
            'completion_notes' => 'nullable|string|max:3000',
        ]);
        $service->markCleaned(
            $task,
            (int) auth()->id(),
            $data['completion_notes'] ?? null
        );

        return back()->with(
            'status',
            ['success' => 1, 'msg' => 'Room marked cleaned and awaiting inspection.']
        );
    }

    public function inspect(
        Request $request,
        HmsHousekeepingTask $task,
        HousekeepingService $service
    ) {
        $businessId = $this->businessId();
        $this->authorizeInspection($businessId);
        $this->ensureTaskBusiness($task, $businessId);
        $data = $request->validate([
            'inspection_result' => 'required|in:passed,failed',
            'inspection_notes' => 'nullable|string|max:3000',
        ]);
        $service->inspect(
            $task,
            (int) auth()->id(),
            $data['inspection_result'] === 'passed',
            $data['inspection_notes'] ?? null
        );

        return back()->with(
            'status',
            [
                'success' => 1,
                'msg' => $data['inspection_result'] === 'passed'
                    ? 'Inspection passed. The room is ready.'
                    : 'Inspection failed. The room was returned for cleaning.',
            ]
        );
    }

    private function businessId(): int
    {
        return (int) request()->session()->get('user.business_id');
    }

    private function ensureHmsAccess(int $businessId): void
    {
        abort_unless(
            auth()->user()->can('superadmin')
            || $this->moduleUtil->hasThePermissionInSubscription($businessId, 'hms_module'),
            403,
            'Unauthorized action.'
        );
    }

    private function authorizeView(int $businessId): void
    {
        $this->ensureHmsAccess($businessId);
        abort_unless(
            auth()->user()->can('superadmin')
            || auth()->user()->can('hms.manage_rooms')
            || auth()->user()->can('hms.manage_housekeeping')
            || auth()->user()->can('hms.perform_housekeeping')
            || auth()->user()->can('hms.inspect_housekeeping'),
            403,
            'Unauthorized action.'
        );
    }

    private function authorizeManage(int $businessId): void
    {
        $this->ensureHmsAccess($businessId);
        abort_unless($this->canManage(), 403, 'Unauthorized action.');
    }

    private function authorizePerform(
        int $businessId,
        HmsHousekeepingTask $task
    ): void {
        $this->ensureHmsAccess($businessId);
        $this->ensureTaskBusiness($task, $businessId);
        abort_unless($this->canPerformTask($task), 403, 'Unauthorized action.');
    }

    private function authorizeInspection(int $businessId): void
    {
        $this->ensureHmsAccess($businessId);
        abort_unless($this->canInspect(), 403, 'Unauthorized action.');
    }

    private function canManage(): bool
    {
        return auth()->user()->can('superadmin')
            || auth()->user()->can('hms.manage_rooms')
            || auth()->user()->can('hms.manage_housekeeping');
    }

    private function canPerformTask(HmsHousekeepingTask $task): bool
    {
        if ($this->canManage()) {
            return true;
        }

        return auth()->user()->can('hms.perform_housekeeping')
            && (empty($task->assigned_to) || (int) $task->assigned_to === (int) auth()->id());
    }

    private function canInspect(): bool
    {
        return $this->canManage()
            || auth()->user()->can('hms.inspect_housekeeping');
    }

    private function ensureTaskBusiness(
        HmsHousekeepingTask $task,
        int $businessId
    ): void {
        abort_unless((int) $task->business_id === $businessId, 403);
    }

    private function ensureUserBelongsToBusiness(int $businessId, int $userId): void
    {
        if (! User::where('business_id', $businessId)->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                'assigned_to' => 'The selected staff member does not belong to this business.',
            ]);
        }
    }

    private function taskTypes(): array
    {
        return [
            'checkout_cleaning' => 'Checkout cleaning',
            'stayover_cleaning' => 'Stay-over cleaning',
            'deep_cleaning' => 'Deep cleaning',
            'inspection' => 'Inspection',
        ];
    }
}
