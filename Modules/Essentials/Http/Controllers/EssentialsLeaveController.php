<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\LeaveAccount;
use Modules\Essentials\Entities\LeaveLedgerEntry;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\LeaveLedgerService;
use Modules\Essentials\Notifications\LeaveStatusNotification;
use Modules\Essentials\Notifications\NewLeaveNotification;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Business;

class EssentialsLeaveController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $leave_statuses;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->leave_statuses = [
            'pending' => [
                'name' => __('lang_v1.pending'),
                'class' => 'bg-yellow',
            ],
            'approved' => [
                'name' => __('essentials::lang.approved'),
                'class' => 'bg-green',
            ],
            'cancelled' => [
                'name' => __('essentials::lang.cancelled'),
                'class' => 'bg-red',
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.crud_all_leave', 'essentials.crud_own_leave']);
        $can_crud_all_leave = auth()->user()->can('superadmin')
            || $this->moduleUtil->is_admin(auth()->user(), $business_id)
            || auth()->user()->canForBusiness('essentials.crud_all_leave', $business_id);
        $can_crud_own_leave = auth()->user()->canForBusiness('essentials.crud_own_leave', $business_id);
        $can_approve_leave = $can_crud_all_leave || auth()->user()->canForBusiness('essentials.approve_leave', $business_id);
        if (request()->ajax()) {
            $leaves = EssentialsLeave::where('essentials_leaves.business_id', $business_id)
                        ->join('users as u', 'u.id', '=', 'essentials_leaves.user_id')
                        ->join('essentials_leave_types as lt', 'lt.id', '=', 'essentials_leaves.essentials_leave_type_id')
                        ->where('u.business_id', $business_id)
                        ->where('lt.business_id', $business_id)
                        ->select([
                            'essentials_leaves.id',
                            DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                            'lt.leave_type',
                            'start_date',
                            'end_date',
                            'ref_no',
                            'essentials_leaves.status',
                            'essentials_leaves.business_id',
                            'reason',
                            'status_note',
                        ]);

            if (! empty(request()->input('user_id'))) {
                $leaves->where('essentials_leaves.user_id', request()->input('user_id'));
            }

            if (! $can_crud_all_leave && $can_crud_own_leave) {
                $leaves->where('essentials_leaves.user_id', auth()->user()->id);
            }

            if (! empty(request()->input('status'))) {
                $leaves->where('essentials_leaves.status', request()->input('status'));
            }

            if (! empty(request()->input('leave_type'))) {
                $leaves->where('essentials_leaves.essentials_leave_type_id', request()->input('leave_type'));
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $leaves->whereDate('essentials_leaves.start_date', '<=', $end)
                            ->whereDate('essentials_leaves.end_date', '>=', $start);
            }

            return Datatables::of($leaves)
                ->addColumn(
                    'action',
                    function ($row) use ($can_crud_all_leave) {
                        $html = '';
                        if ($can_crud_all_leave) {
                            $html .= '<button class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete-leave" data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'destroy'], [$row->id]).'"><i class="fa fa-trash"></i> '.__('messages.delete').'</button>';
                        }

                        $html .= '&nbsp;<button class="tw-dw-btn tw-dw-btn-info tw-text-white tw-dw-btn-xs btn-modal" data-container=".view_modal"  data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'activity'], [$row->id]).'"><i class="fa fa-edit"></i> '.__('essentials::lang.activity').'</button>';

                        return $html;
                    }
                )
                ->editColumn('start_date', function ($row) {
                    $start_date = \Carbon::parse($row->start_date);
                    $end_date = \Carbon::parse($row->end_date);

                    $diff = $start_date->diffInDays($end_date);
                    $diff += 1;
                    $start_date_formated = $this->moduleUtil->format_date($start_date);
                    $end_date_formated = $this->moduleUtil->format_date($end_date);

                    return $start_date_formated.' - '.$end_date_formated.' ('.$diff.' '.\Str::plural(__('lang_v1.day'), $diff).')';
                })
                ->editColumn('status', function ($row) use ($can_approve_leave) {
                    $status_details = $this->leave_statuses[$row->status] ?? $this->leave_statuses['pending'];
                    $status = '<span class="label '.$status_details['class'].'">'
                    .$status_details['name'].'</span>';

                    if ($can_approve_leave) {
                        $status = '<a href="#" class="change_status" data-status_note="'.e($row->status_note).'" data-leave-id="'.$row->id.'" data-orig-value="'.e($row->status).'" data-status-name="'.e($status_details['name']).'"> '.$status.'</a>';
                    }

                    return $status;
                })
                ->filterColumn('user', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'status'])
                ->make(true);
        }
        $users = [];
        if ($can_crud_all_leave || auth()->user()->canForBusiness('essentials.approve_leave', $business_id)) {
            $users = User::forDropdown($business_id, false);
        }
        $leave_statuses = $this->leave_statuses;

        $leave_types = EssentialsLeaveType::forDropdown($business_id);

        return view('essentials::leave.index')->with(compact('leave_statuses', 'users', 'leave_types'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.crud_all_leave', 'essentials.crud_own_leave']);

        $leave_types = EssentialsLeaveType::forDropdown($business_id);

        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];

        $instructions = ! empty($settings['leave_instructions']) ? $settings['leave_instructions'] : '';

        $employees = [];
        if (auth()->user()->can('superadmin') || $this->moduleUtil->is_admin(auth()->user(), $business_id) || auth()->user()->canForBusiness('essentials.crud_all_leave', $business_id)) {
            $employees = User::forDropdown($business_id, false, false, false, true);
        }

        return view('essentials::leave.create')->with(compact('leave_types', 'instructions', 'employees'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.crud_all_leave', 'essentials.crud_own_leave']);
        $can_crud_all_leave = auth()->user()->can('superadmin')
            || $this->moduleUtil->is_admin(auth()->user(), $business_id)
            || auth()->user()->canForBusiness('essentials.crud_all_leave', $business_id);
        $can_crud_own_leave = auth()->user()->canForBusiness('essentials.crud_own_leave', $business_id);

        try {
            $validated = $request->validate([
                'essentials_leave_type_id' => ['required', 'integer'],
                'start_date' => ['required', 'string', 'max:100'],
                'end_date' => ['required', 'string', 'max:100'],
                'reason' => ['nullable', 'string', 'max:2000'],
                'employees' => ['nullable', 'array', 'max:500'],
                'employees.*' => ['integer'],
            ]);
            EssentialsLeaveType::where('business_id', $business_id)->findOrFail($validated['essentials_leave_type_id']);
            $start_date = $this->parseLeaveDate($validated['start_date'], 'start_date');
            $end_date = $this->parseLeaveDate($validated['end_date'], 'end_date');
            if (\Carbon::parse($end_date)->lessThan(\Carbon::parse($start_date))) {
                throw ValidationException::withMessages([
                    'end_date' => __('validation.after_or_equal', ['attribute' => __('essentials::lang.end_date'), 'date' => __('essentials::lang.start_date')]),
                ]);
            }

            $user_ids = $can_crud_all_leave && ! empty($validated['employees'])
                ? array_values(array_unique(array_map('intval', $validated['employees'])))
                : [(int) auth()->user()->id];
            if (User::forBusiness($business_id)->user()->whereIn('id', $user_ids)->count() !== count($user_ids)) {
                throw ValidationException::withMessages([
                    'employees' => __('validation.exists', ['attribute' => __('essentials::lang.employee')]),
                ]);
            }

            $input = [
                'essentials_leave_type_id' => $validated['essentials_leave_type_id'],
                'business_id' => $business_id,
                'status' => 'pending',
                'start_date' => $start_date,
                'end_date' => $end_date,
                'reason' => $validated['reason'] ?? null,
            ];

            $leaves = DB::transaction(function () use ($business_id, $input, $user_ids, $start_date, $end_date) {
                Business::whereKey($business_id)->lockForUpdate()->firstOrFail();
                foreach ($user_ids as $user_id) {
                    if ($this->leaveOverlapExists($business_id, $user_id, $start_date, $end_date)) {
                        throw ValidationException::withMessages([
                            'start_date' => 'The selected employee already has pending or approved leave during this period.',
                        ]);
                    }
                }

                $created = [];
                foreach ($user_ids as $user_id) {
                    $created[] = $this->__addLeave($input, $user_id);
                }

                return $created;
            });

            foreach ($leaves as $leave) {
                try {
                    $admins = $this->moduleUtil->get_admins($business_id);
                    \Notification::send($admins, new NewLeaveNotification($leave));
                } catch (\Throwable $notification_error) {
                    \Log::warning('Leave saved but notification failed: '.$notification_error->getMessage());
                }
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    private function __addLeave($input, $user_id = null)
    {
        $input['user_id'] = ! empty($user_id) ? $user_id : request()->session()->get('user.id');
        //Update reference count
        $ref_count = $this->moduleUtil->setAndGetReferenceCount('leave');
        //Generate reference number
        if (empty($input['ref_no'])) {
            $settings = request()->session()->get('business.essentials_settings');
            $settings = ! empty($settings) ? json_decode($settings, true) : [];
            $prefix = ! empty($settings['leave_ref_no_prefix']) ? $settings['leave_ref_no_prefix'] : '';
            $input['ref_no'] = $this->moduleUtil->generateReferenceNumber('leave', $ref_count, null, $prefix);
        }

        return EssentialsLeave::create($input);
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        return view('essentials::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit()
    {
        return view('essentials::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_leave');

        if (request()->ajax()) {
            try {
                EssentialsLeave::where('business_id', $business_id)->where('id', $id)->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function changeStatus(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.approve_leave');

        try {
            $input = $request->validate([
                'status' => ['required', 'in:pending,approved,cancelled'],
                'leave_id' => ['required', 'integer'],
                'status_note' => ['nullable', 'string', 'max:2000'],
                'is_additional' => ['nullable', 'boolean'],
            ]);
            $leave = DB::transaction(function () use ($business_id, $input) {
                $leave = EssentialsLeave::where('business_id', $business_id)
                    ->lockForUpdate()
                    ->findOrFail($input['leave_id']);
                $previous_status = $leave->status;

                $allowed_transitions = [
                    'pending' => ['pending', 'approved', 'cancelled'],
                    'approved' => ['approved', 'cancelled'],
                    'cancelled' => ['cancelled', 'pending'],
                ];
                if (! in_array($input['status'], $allowed_transitions[$leave->status] ?? [], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'This leave status transition is not allowed.',
                    ]);
                }

                $is_additional = ! empty($input['is_additional']);
                $profile = EmploymentProfile::forBusiness($business_id)->where('user_id', $leave->user_id)->first();
                $requested_days = $profile
                    ? app(LeaveLedgerService::class)->workingDaysFor($profile, $leave->start_date, $leave->end_date)
                    : \Carbon::parse($leave->start_date)->diffInDays(\Carbon::parse($leave->end_date)) + 1;
                if ($requested_days <= 0) throw ValidationException::withMessages(['start_date' => 'The request contains no chargeable work days under the employee calendar.']);
                if ($input['status'] === 'approved') {
                    $leave_type = EssentialsLeaveType::where('business_id', $business_id)
                        ->findOrFail($leave->essentials_leave_type_id);
                    if (! empty($leave_type->max_leave_count)) {
                        $already_taken = $this->checkLeaveAvailability($leave);
                        if (($already_taken + $requested_days) > $leave_type->max_leave_count && ! $is_additional) {
                            throw ValidationException::withMessages([
                                'is_additional' => 'This request exceeds the leave allowance. Mark it as additional leave to approve it explicitly.',
                            ]);
                        }
                    }
                }

                $leave->is_additional = $input['status'] === 'approved' ? $is_additional : false;
                $leave->status = $input['status'];
                $leave->status_note = $input['status_note'] ?? null;
                $leave->changed_by = auth()->user()->id;
                $leave->save();

                // When a governed leave account exists, mirror the approved
                // legacy leave into the immutable ledger exactly once.
                if ($previous_status !== $input['status'] && in_array($input['status'], ['approved', 'cancelled'], true)) {
                    $policy_period = \Carbon::parse($leave->start_date)->format('Y');
                    $account = $profile ? LeaveAccount::forBusiness($business_id)
                        ->where('employment_profile_id', $profile->id)
                        ->where('leave_type_id', $leave->essentials_leave_type_id)
                        ->where('policy_period', $policy_period)
                        ->first() : null;
                    if ($account) {
                        $days = $requested_days;
                        $ledger = app(LeaveLedgerService::class);
                        if ($input['status'] === 'approved' && ! LeaveLedgerEntry::where('leave_id', $leave->id)->where('entry_type', 'usage')->exists()) {
                            if ($is_additional) {
                                $ledger->post($account, 'adjustment', $days, $leave->start_date->format('Y-m-d'), 'Additional leave explicitly approved', (int) auth()->id(), 'leave', $leave->id);
                                $account = $account->fresh();
                            }
                            $ledger->post($account, 'usage', $days, $leave->start_date->format('Y-m-d'), 'Approved leave '.$leave->ref_no, (int) auth()->id(), 'leave', $leave->id);
                        }
                        if ($input['status'] === 'cancelled' && $previous_status === 'approved' && ! LeaveLedgerEntry::where('leave_id', $leave->id)->where('entry_type', 'adjustment')->where('reason', 'like', 'Cancelled approved leave%')->exists()) {
                            $ledger->post($account, 'adjustment', $days, now()->toDateString(), 'Cancelled approved leave '.$leave->ref_no, (int) auth()->id(), 'leave', $leave->id);
                        }
                    }
                }

                return $leave->fresh(['user', 'changed_by_user']);
            });

            try {
                $leave->status = $this->leave_statuses[$leave->status]['name'];
                $leave->user->notify(new LeaveStatusNotification($leave));
            } catch (\Throwable $notification_error) {
                \Log::warning('Leave status saved but notification failed: '.$notification_error->getMessage());
            }

            $output = ['success' => true,
                'msg' => __('lang_v1.updated_success'),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Function to show activity log related to a leave
     *
     * @return Response
     */
    public function activity($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.crud_all_leave', 'essentials.crud_own_leave']);

        $leave_query = EssentialsLeave::where('business_id', $business_id);
        if (! auth()->user()->can('superadmin')
            && ! $this->moduleUtil->is_admin(auth()->user(), $business_id)
            && ! auth()->user()->canForBusiness('essentials.crud_all_leave', $business_id)) {
            $leave_query->where('user_id', auth()->user()->id);
        }
        $leave = $leave_query->findOrFail($id);

        $activities = Activity::forSubject($leave)
                           ->with(['causer', 'subject'])
                           ->latest()
                           ->get();

        return view('essentials::leave.activity_modal')->with(compact('leave', 'activities'));
    }

    /**
     * Function to get leave summary of a user
     *
     * @return Response
     */
    public function getUserLeaveSummary()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.crud_all_leave', 'essentials.crud_own_leave']);
        $can_view_all = auth()->user()->can('superadmin')
            || $this->moduleUtil->is_admin(auth()->user(), $business_id)
            || auth()->user()->canForBusiness('essentials.crud_all_leave', $business_id);
        $user_id = $can_view_all && request()->filled('user_id')
            ? (int) request()->input('user_id')
            : (int) auth()->user()->id;
        request()->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $user = User::forBusiness($business_id)->findOrFail($user_id);

        $query = EssentialsLeave::where('business_id', $business_id)
                            ->where('user_id', $user_id)
                            ->with(['leave_type'])
                            ->select(
                                'status',
                                'essentials_leave_type_id',
                                'start_date',
                                'end_date'
                            );

        $filter_start = ! empty(request()->start_date) ? \Carbon::parse(request()->start_date)->startOfDay() : null;
        $filter_end = ! empty(request()->end_date) ? \Carbon::parse(request()->end_date)->endOfDay() : null;
        if (! empty($filter_start) && ! empty($filter_end)) {
            $query->whereDate('start_date', '<=', $filter_end)
                        ->whereDate('end_date', '>=', $filter_start);
        }
        $leaves = $query->get();
        $statuses = $this->leave_statuses;
        $leaves_summary = [];
        $status_summary = [];

        foreach ($statuses as $key => $value) {
            $status_summary[$key] = 0;
        }
        foreach ($leaves as $leave) {
            $start_date = \Carbon::parse($leave->start_date);
            $end_date = \Carbon::parse($leave->end_date);
            if (! empty($filter_start)) {
                $start_date = $start_date->max($filter_start);
            }
            if (! empty($filter_end)) {
                $end_date = $end_date->min($filter_end);
            }
            $diff = $start_date->diffInDays($end_date) + 1;

            $leaves_summary[$leave->essentials_leave_type_id][$leave->status] =
            isset($leaves_summary[$leave->essentials_leave_type_id][$leave->status]) ?
            $leaves_summary[$leave->essentials_leave_type_id][$leave->status] + $diff : $diff;

            $status_summary[$leave->status] = isset($status_summary[$leave->status]) ? ($status_summary[$leave->status] + $diff) : $diff;
        }

        $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                                    ->get();
        return view('essentials::leave.user_leave_summary')->with(compact('leaves_summary', 'leave_types', 'statuses', 'user', 'status_summary'));
    }

    public function changeLeaveStatus(Request $request) {

        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.approve_leave');
        $request->validate(['id' => ['required', 'integer']]);

        $current_leave = EssentialsLeave::where('business_id', $business_id)->findOrFail($request->id);

        $leave_statuses = $this->leave_statuses;

        $leaveCount =  $this->checkLeaveAvailability($current_leave);

        $leaveType = EssentialsLeaveType::where('business_id', $business_id)
            ->findOrFail($current_leave->essentials_leave_type_id);

        return view('essentials::leave.change_status_modal', compact('leave_statuses', 'current_leave', 'leaveType', 'leaveCount'));
    }

    /**
     * Checks the availability of leaves for a given leave type and user.
     * 
     * This method calculates the total days of approved leaves taken by a user within a specific period
     * based on the leave type's count interval (month, year, or lifetime). It excludes the current leave
     * being processed.
     * 
     * @param EssentialsLeave $currentLeave The current leave being processed.
     * @return int The total days of approved leaves taken by the user within the specified period.
     */
    public function checkLeaveAvailability($currentLeave)
    {
        // Fetch the leave type associated with the current leave
        $leaveType = EssentialsLeaveType::where('business_id', $currentLeave->business_id)
            ->find($currentLeave->essentials_leave_type_id);
    
        if (!$leaveType) {
            return ['status' => false, 'message' => 'Leave type not found'];
        }
    
        // Initialize the leave query to filter leaves based on user, leave type, and status
        $leaveQuery = EssentialsLeave::where('user_id', $currentLeave->user_id)
            ->where('business_id', $currentLeave->business_id)
            ->where('essentials_leave_type_id', $currentLeave->essentials_leave_type_id)
            ->where('status', 'approved') // Assuming 'approved' status
            ->where('id', '!=', $currentLeave->id);
        // Determine the start date based on the leave_count_interval
        $period_start = null;
        $period_end = null;
        if ($leaveType->leave_count_interval === 'month') {
            $leaveStartDate = \Carbon::parse($currentLeave->start_date);
            $period_start = $leaveStartDate->copy()->startOfMonth();
            $period_end = $leaveStartDate->copy()->endOfMonth();
        } elseif ($leaveType->leave_count_interval === 'year') {
            // Count days taken only in the financial year of the leave's start_date
            $leaveStartDate = \Carbon::parse($currentLeave->start_date);
            $currentYear = $leaveStartDate->year;
    
            // Determine the financial year start based on the business's fiscal year start month
            $business = Business::where('id', $currentLeave->business_id)->firstOrFail();
            $start_month = $business->fy_start_month ?: 1;

            $period_start = $leaveStartDate->month >= $start_month
                ? \Carbon::createFromDate($currentYear, $start_month, 1) 
                : \Carbon::createFromDate($currentYear - 1, $start_month, 1);

            $period_end = $period_start->copy()->addYear()->subDay();
        } // No action needed if leave_count_interval is null (lifetime)

        if (! empty($period_start) && ! empty($period_end)) {
            $leaveQuery->whereDate('start_date', '<=', $period_end)
                ->whereDate('end_date', '>=', $period_start);
        }
    
        // Sum the days of each approved leave
        $profile = EmploymentProfile::forBusiness($currentLeave->business_id)->where('user_id', $currentLeave->user_id)->first();
        $leaveCount = $leaveQuery->get()->sum(function ($leave) use ($period_start, $period_end, $profile) {
            // Calculate total days in each leave (end_date inclusive)
            $start = \Carbon::parse($leave->start_date);
            $end = \Carbon::parse($leave->end_date);
            if (! empty($period_start)) {
                $start = $start->max($period_start);
            }
            if (! empty($period_end)) {
                $end = $end->min($period_end);
            }
            return $profile
                ? app(LeaveLedgerService::class)->workingDaysFor($profile, $start, $end)
                : $start->diffInDays($end) + 1;
        });
    
        return $leaveCount;
    }

    private function parseLeaveDate($value, string $field): string
    {
        try {
            $parsed = $this->moduleUtil->uf_date($value);

            return \Carbon::parse($parsed)->format('Y-m-d');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                $field => __('validation.date', ['attribute' => $field]),
            ]);
        }
    }

    private function leaveOverlapExists(int $business_id, int $user_id, string $start_date, string $end_date): bool
    {
        return EssentialsLeave::where('business_id', $business_id)
            ->where('user_id', $user_id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end_date)
            ->whereDate('end_date', '>=', $start_date)
            ->exists();
    }
}
