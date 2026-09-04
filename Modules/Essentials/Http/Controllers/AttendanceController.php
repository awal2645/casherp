<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Utils\EssentialsUtil;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class AttendanceController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $essentialsUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, EssentialsUtil $essentialsUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->essentialsUtil = $essentialsUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, [
            'essentials.crud_all_attendance',
            'essentials.view_own_attendance',
        ]);
        $can_crud_all_attendance = auth()->user()->canForBusiness('essentials.crud_all_attendance', $business_id);
        $can_view_own_attendance = auth()->user()->canForBusiness('essentials.view_own_attendance', $business_id);

        if (! $can_crud_all_attendance && ! $can_view_own_attendance) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $attendance = EssentialsAttendance::where('essentials_attendances.business_id', $business_id)
                            ->join('users as u', 'u.id', '=', 'essentials_attendances.user_id')
                            ->where(function ($query) use ($business_id) {
                                $query->where('u.business_id', $business_id)
                                    ->orWhereExists(function ($subquery) use ($business_id) {
                                        $subquery->select(DB::raw(1))
                                            ->from('hrm_employment_profiles as hep')
                                            ->whereColumn('hep.user_id', 'u.id')
                                            ->where('hep.business_id', $business_id)
                                            ->whereNull('hep.deleted_at');
                                    });
                            })
                            ->leftJoin('essentials_shifts as es', function ($join) use ($business_id) {
                                $join->on('es.id', '=', 'essentials_attendances.essentials_shift_id')
                                    ->where('es.business_id', '=', $business_id);
                            })
                            ->select([
                                'essentials_attendances.id',
                                'clock_in_time',
                                'clock_out_time',
                                'clock_in_note',
                                'clock_out_note',
                                'ip_address',
                                DB::raw('DATE(clock_in_time) as date'),
                                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                                'es.name as shift_name', 'clock_in_location', 'clock_out_location',
                            ])->groupBy('essentials_attendances.id');

            $permitted_locations = auth()->user()->permitted_locations();

            if ($permitted_locations != 'all') {
                $permitted_locations_array = [];

                foreach ($permitted_locations as $loc_id) {
                    $permitted_locations_array[] = 'location.'.$loc_id;
                }
                $permission_ids = Permission::whereIn('name', $permitted_locations_array)
                                        ->pluck('id');

                $attendance->join('model_has_permissions as mhp', 'mhp.model_id', '=', 'u.id')->whereIn('mhp.permission_id', $permission_ids);
            }

            if (! empty(request()->input('employee_id'))) {
                $attendance->where('essentials_attendances.user_id', request()->input('employee_id'));
            }
            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $attendance->whereDate('clock_in_time', '>=', $start)
                            ->whereDate('clock_in_time', '<=', $end);
            }

            if (! $can_crud_all_attendance && $can_view_own_attendance) {
                $attendance->where('essentials_attendances.user_id', auth()->user()->id);
            }

            return Datatables::of($attendance)
                    ->addColumn(
                        'action',
                        '@can("essentials.crud_all_attendance") <button data-href="{{action(\'\Modules\Essentials\Http\Controllers\AttendanceController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container="#edit_attendance_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        <button class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete-attendance" data-href="{{action(\'\Modules\Essentials\Http\Controllers\AttendanceController@destroy\', [$id])}}"><i class="fa fa-trash"></i> @lang("messages.delete")</button> @endcan
                        '
                    )
                    ->editColumn('work_duration', function ($row) {
                        $clock_in = \Carbon::parse($row->clock_in_time);
                        if (! empty($row->clock_out_time)) {
                            $clock_out = \Carbon::parse($row->clock_out_time);
                        } else {
                            $clock_out = \Carbon::now();
                        }

                        $html = $clock_in->diffForHumans($clock_out, true, true, 2);

                        return $html;
                    })
                    ->editColumn('clock_in', function ($row) {
                        $html = $this->moduleUtil->format_date($row->clock_in_time, true);
                        if (! empty($row->clock_in_location)) {
                            $html .= '<br>'.e($row->clock_in_location).'<br>';
                        }

                        if (! empty($row->clock_in_note)) {
                            $html .= '<br>'.e($row->clock_in_note).'<br>';
                        }

                        return $html;
                    })
                    ->editColumn('clock_out', function ($row) {
                        $html = $this->moduleUtil->format_date($row->clock_out_time, true);
                        if (! empty($row->clock_out_location)) {
                            $html .= '<br>'.e($row->clock_out_location).'<br>';
                        }

                        if (! empty($row->clock_out_note)) {
                            $html .= '<br>'.e($row->clock_out_note).'<br>';
                        }

                        return $html;
                    })
                    ->editColumn('date', '{{@format_date($date)}}')
                    ->rawColumns(['action', 'clock_in', 'work_duration', 'clock_out'])
                    ->filterColumn('user', function ($query, $keyword) {
                        $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                    })
                    ->make(true);
        }

        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];

        $is_employee_allowed = auth()->user()->canForBusiness('essentials.allow_users_for_attendance_from_web', $business_id);
        $clock_in = EssentialsAttendance::where('business_id', $business_id)
                                ->where('user_id', auth()->user()->id)
                                ->whereNull('clock_out_time')
                                ->first();
        $employees = [];
        if ($can_crud_all_attendance) {
            $employees = User::forDropdown($business_id, false);
        }

        $days = $this->moduleUtil->getDays();

        return view('essentials::attendance.index')
            ->with(compact('is_employee_allowed', 'clock_in', 'employees', 'days'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        $employees = User::forDropdown($business_id, false);

        return view('essentials::attendance.create')->with(compact('employees'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        try {
            $validated = $request->validate([
                'attendance' => ['required', 'array', 'min:1'],
                'attendance.*.id' => ['nullable', 'integer'],
                'attendance.*.clock_in_time' => ['nullable', 'string', 'max:100'],
                'attendance.*.clock_out_time' => ['nullable', 'string', 'max:100'],
                'attendance.*.ip_address' => ['nullable', 'ip'],
                'attendance.*.clock_in_note' => ['nullable', 'string', 'max:2000'],
                'attendance.*.clock_out_note' => ['nullable', 'string', 'max:2000'],
                'attendance.*.essentials_shift_id' => ['nullable', 'integer'],
            ]);

            $attendance = $validated['attendance'];
            $user_ids = array_map('intval', array_keys($attendance));
            $valid_user_ids = User::forBusiness($business_id)
                ->whereIn('id', $user_ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count(array_unique($user_ids)) !== count($valid_user_ids)) {
                throw ValidationException::withMessages([
                    'attendance' => __('essentials::lang.employee_not_found'),
                ]);
            }

            $ip_address = $this->moduleUtil->getUserIpAddr();
            DB::transaction(function () use ($attendance, $business_id, $ip_address) {
                foreach ($attendance as $user_id => $value) {
                    // The employee row is the serialization key for every way an
                    // attendance interval can be created for this person.
                    User::forBusiness($business_id)
                        ->whereKey((int) $user_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $existing = null;
                    if (! empty($value['id'])) {
                        $existing = EssentialsAttendance::where('business_id', $business_id)
                            ->where('user_id', $user_id)
                            ->lockForUpdate()
                            ->findOrFail($value['id']);
                    }

                    $clock_in = ! empty($value['clock_in_time'])
                        ? $this->parseAttendanceDate($value['clock_in_time'], "attendance.$user_id.clock_in_time")
                        : optional($existing)->clock_in_time;
                    if (empty($clock_in)) {
                        throw ValidationException::withMessages([
                            "attendance.$user_id.clock_in_time" => __('validation.required', ['attribute' => __('essentials::lang.clock_in_time')]),
                        ]);
                    }

                    $clock_out = ! empty($value['clock_out_time'])
                        ? $this->parseAttendanceDate($value['clock_out_time'], "attendance.$user_id.clock_out_time")
                        : null;
                    $this->validateAttendanceRange($business_id, (int) $user_id, $clock_in, $clock_out, optional($existing)->id);

                    $shift_id = $value['essentials_shift_id'] ?? optional($existing)->essentials_shift_id;
                    if (! empty($shift_id) && ! Shift::where('business_id', $business_id)->whereKey($shift_id)->exists()) {
                        throw ValidationException::withMessages([
                            "attendance.$user_id.essentials_shift_id" => __('validation.exists', ['attribute' => __('essentials::lang.shift')]),
                        ]);
                    }

                    $record = $existing ?: new EssentialsAttendance();
                    $record->fill([
                        'business_id' => $business_id,
                        'user_id' => (int) $user_id,
                        'clock_in_time' => $clock_in,
                        'clock_out_time' => $clock_out,
                        'ip_address' => $value['ip_address'] ?? $ip_address,
                        'clock_in_note' => $value['clock_in_note'] ?? optional($existing)->clock_in_note,
                        'clock_out_note' => $value['clock_out_note'] ?? null,
                        'essentials_shift_id' => $shift_id,
                    ]);
                    $record->save();
                }
            });

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

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        $attendance = EssentialsAttendance::where('business_id', $business_id)
                                    ->with(['employee'])
                                    ->findOrFail($id);

        return view('essentials::attendance.edit')->with(compact('attendance'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        try {
            $input = $request->validate([
                'clock_in_time' => ['required', 'string', 'max:100'],
                'clock_out_time' => ['nullable', 'string', 'max:100'],
                'ip_address' => ['nullable', 'ip'],
                'clock_in_note' => ['nullable', 'string', 'max:2000'],
                'clock_out_note' => ['nullable', 'string', 'max:2000'],
            ]);

            DB::transaction(function () use ($business_id, $id, $input) {
                $attendance = EssentialsAttendance::where('business_id', $business_id)
                    ->lockForUpdate()
                    ->findOrFail($id);
                $clock_in = $this->parseAttendanceDate($input['clock_in_time'], 'clock_in_time');
                $clock_out = ! empty($input['clock_out_time'])
                    ? $this->parseAttendanceDate($input['clock_out_time'], 'clock_out_time')
                    : null;
                $this->validateAttendanceRange($business_id, (int) $attendance->user_id, $clock_in, $clock_out, (int) $attendance->id);

                $attendance->update([
                    'clock_in_time' => $clock_in,
                    'clock_out_time' => $clock_out,
                    'ip_address' => $input['ip_address'] ?? $attendance->ip_address,
                    'clock_in_note' => $input['clock_in_note'] ?? null,
                    'clock_out_note' => $input['clock_out_note'] ?? null,
                ]);
            });
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
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        if (request()->ajax()) {
            try {
                EssentialsAttendance::where('business_id', $business_id)->where('id', $id)->delete();

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

    /**
     * Clock in / Clock out the logged in user.
     *
     * @return Response
     */
    public function clockInClockOut(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $this->authorizeHrmFeature($business_id);

        $input = $request->validate([
            'type' => ['required', 'in:clock_in,clock_out'],
            'clock_in_note' => ['nullable', 'string', 'max:2000'],
            'clock_out_note' => ['nullable', 'string', 'max:2000'],
            'clock_in_out_location' => ['nullable', 'string', 'max:1000'],
        ]);

        //Check if employees allowed to add their own attendance
        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];
        if (! auth()->user()->canForBusiness('essentials.allow_users_for_attendance_from_web', $business_id)) {
            return ['success' => false,
                'msg' => __('essentials::lang.not_allowed'),
            ];
        } elseif ((! empty($settings['is_location_required']) && $settings['is_location_required']) && empty($input['clock_in_out_location'])) {
            return ['success' => false,
                'msg' => __('essentials::lang.you_must_enable_location'),
            ];
        }

        try {
            $type = $input['type'];

            if ($type == 'clock_in') {
                $data = [
                    'business_id' => $business_id,
                    'user_id' => auth()->user()->id,
                    'clock_in_time' => \Carbon::now(),
                    'clock_in_note' => $input['clock_in_note'] ?? null,
                    'ip_address' => $this->moduleUtil->getUserIpAddr(),
                    'clock_in_location' => $input['clock_in_out_location'] ?? null,
                ];

                $output = $this->essentialsUtil->clockin($data, $settings);
            } elseif ($type == 'clock_out') {
                $data = [
                    'business_id' => $business_id,
                    'user_id' => auth()->user()->id,
                    'clock_out_time' => \Carbon::now(),
                    'clock_out_note' => $input['clock_out_note'] ?? null,
                    'clock_out_location' => $input['clock_in_out_location'] ?? null,
                ];

                $output = $this->essentialsUtil->clockout($data, $settings);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
                'type' => $type,
            ];
        }

        return $output;
    }

    /**
     * Function to get attendance summary of a user
     *
     * @return Response
     */
    public function getUserAttendanceSummary()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, [
            'essentials.crud_all_attendance',
            'essentials.view_own_attendance',
        ]);

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        $can_view_all = auth()->user()->can('superadmin') || $is_admin || auth()->user()->canForBusiness('essentials.crud_all_attendance', $business_id);
        $user_id = $can_view_all && request()->filled('user_id')
            ? (int) request()->input('user_id')
            : (int) auth()->user()->id;

        User::forBusiness($business_id)->findOrFail($user_id);

        request()->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $start_date = ! empty(request()->start_date) ? request()->start_date : null;
        $end_date = ! empty(request()->end_date) ? request()->end_date : null;

        $total_work_duration = $this->essentialsUtil->getTotalWorkDuration('hour', $user_id, $business_id, $start_date, $end_date);

        return $total_work_duration;
    }

    /**
     * Function to validate clock in and clock out time
     *
     * @return string
     */
    public function validateClockInClockOut(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        $input = $request->validate([
            'user_ids' => ['required', 'string', 'max:2000'],
            'clock_in_time' => ['required', 'string', 'max:100'],
            'clock_out_time' => ['nullable', 'string', 'max:100'],
            'attendance_id' => ['nullable', 'integer'],
        ]);
        $user_ids = collect(explode(',', $input['user_ids']))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($user_ids) || User::forBusiness($business_id)->whereIn('id', $user_ids)->count() !== count($user_ids)) {
            return 'false';
        }

        $clock_in = $this->parseAttendanceDate($input['clock_in_time'], 'clock_in_time');
        $clock_out = ! empty($input['clock_out_time'])
            ? $this->parseAttendanceDate($input['clock_out_time'], 'clock_out_time')
            : null;
        if (! empty($clock_out) && \Carbon::parse($clock_out)->lessThanOrEqualTo(\Carbon::parse($clock_in))) {
            return 'false';
        }

        foreach ($user_ids as $user_id) {
            if ($this->attendanceOverlapExists($business_id, $user_id, $clock_in, $clock_out, $input['attendance_id'] ?? null)) {
                return 'false';
            }
        }

        return 'true';
    }

    /**
     * Get attendance summary by shift
     */
    public function getAttendanceByShift()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        request()->validate(['date' => ['required', 'date']]);

        $date = $this->moduleUtil->uf_date(request()->input('date'));

        $attendance_data = EssentialsAttendance::where('business_id', $business_id)
                                ->whereDate('clock_in_time', $date)
                                ->whereNotNull('essentials_shift_id')
                                ->whereHas('shift', function ($query) use ($business_id) {
                                    $query->where('business_id', $business_id);
                                })
                                ->whereHas('employee', function ($query) use ($business_id) {
                                    $query->forBusiness($business_id);
                                })
                                ->with([
                                    'shift' => function ($query) use ($business_id) {
                                        $query->where('business_id', $business_id);
                                    },
                                    'shift.user_shifts',
                                    'shift.user_shifts.user' => function ($query) use ($business_id) {
                                        $query->where('business_id', $business_id);
                                    },
                                    'employee' => function ($query) use ($business_id) {
                                        $query->forBusiness($business_id);
                                    },
                                ])
                                ->get();
        $attendance_by_shift = [];
        $date_obj = \Carbon::parse($date);
        foreach ($attendance_data as $data) {
            if (empty($attendance_by_shift[$data->essentials_shift_id])) {
                //Calculate total users in the shift
                $total_users = 0;
                $all_users = [];
                foreach ($data->shift->user_shifts as $user_shift) {
                    if (! empty($user_shift->user)
                        && ! empty($user_shift->start_date)
                        && $date_obj->greaterThanOrEqualTo(\Carbon::parse($user_shift->start_date))
                        && (empty($user_shift->end_date) || $date_obj->lessThanOrEqualTo(\Carbon::parse($user_shift->end_date)))) {
                        $total_users++;
                        $all_users[] = $user_shift->user->user_full_name;
                    }
                }
                $attendance_by_shift[$data->essentials_shift_id] = [
                    'present' => 1,
                    'shift' => $data->shift->name,
                    'total' => $total_users,
                    'present_users' => [$data->employee->user_full_name],
                    'all_users' => $all_users,
                ];
            } else {
                if (! in_array($data->employee->user_full_name, $attendance_by_shift[$data->essentials_shift_id]['present_users'])) {
                    $attendance_by_shift[$data->essentials_shift_id]['present']++;
                    $attendance_by_shift[$data->essentials_shift_id]['present_users'][] = $data->employee->user_full_name;
                }
            }
        }

        return view('essentials::attendance.attendance_by_shift_data')->with(compact('attendance_by_shift'));
    }

    /**
     * Get attendance summary by date
     */
    public function getAttendanceByDate()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        request()->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start_date = request()->input('start_date');
        $end_date = request()->input('end_date');

        $attendance_data = EssentialsAttendance::where('business_id', $business_id)
                                ->whereDate('clock_in_time', '>=', $start_date)
                                ->whereDate('clock_in_time', '<=', $end_date)
                                ->select(
                                    'essentials_attendances.*',
                                    DB::raw('COUNT(DISTINCT essentials_attendances.user_id) as total_present'),
                                    DB::raw('CAST(clock_in_time AS DATE) as clock_in_date')
                                )
                                ->groupBy(DB::raw('CAST(clock_in_time AS DATE)'))
                                ->get();

        $all_users = User::forBusiness($business_id)
                        ->user()
                        ->count();

        $attendance_by_date = [];
        foreach ($attendance_data as $data) {
            $total_present = ! empty($data->total_present) ? $data->total_present : 0;
            $attendance_by_date[] = [
                'present' => $total_present,
                'absent' => $all_users - $total_present,
                'date' => $data->clock_in_date,
            ];
        }

        return view('essentials::attendance.attendance_by_date_data')->with(compact('attendance_by_date'));
    }

    /**
     * Function to import attendance.
     *
     * @param  Request  $request
     * @return Response
     */
    public function importAttendance(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        try {
            $request->validate([
                'attendance' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            ]);

            $notAllowed = $this->moduleUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);

            if ($request->hasFile('attendance')) {
                $file = $request->file('attendance');
                $parsed_array = Excel::toArray([], $file);
                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);
                if (count($imported_data) > 10000) {
                    throw ValidationException::withMessages([
                        'attendance' => __('validation.max.array', ['attribute' => 'attendance rows', 'max' => 10000]),
                    ]);
                }

                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                DB::beginTransaction();
                $ip_address = $this->moduleUtil->getUserIpAddr();
                User::forBusiness($business_id)
                    ->whereIn('email', collect($imported_data)->pluck(0)->filter()->map(fn ($email) => trim((string) $email))->unique())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);
                foreach ($imported_data as $key => $value) {
                    $row_no = $key + 2;
                    $temp = [];

                    //Add user
                    if (! empty($value[0])) {
                        $email = trim($value[0]);
                        $user = User::forBusiness($business_id)->where('email', $email)->first();
                        if (! empty($user)) {
                            $temp['user_id'] = $user->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "User not found in row no. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Email is required in row no. $row_no";
                        break;
                    }

                    //clockin time
                    if (! empty($value[1])) {
                        $temp['clock_in_time'] = $this->parseImportedAttendanceDate($value[1], "Clock in time in row no. $row_no");
                    } else {
                        $is_valid = false;
                        $error_msg = "Clock in time is required in row no. $row_no";
                        break;
                    }
                    $temp['clock_out_time'] = ! empty($value[2])
                        ? $this->parseImportedAttendanceDate($value[2], "Clock out time in row no. $row_no")
                        : null;
                    if (! empty($temp['clock_out_time']) && \Carbon::parse($temp['clock_out_time'])->lessThanOrEqualTo(\Carbon::parse($temp['clock_in_time']))) {
                        $is_valid = false;
                        $error_msg = "Clock out time must be after clock in time in row no. $row_no";
                        break;
                    }

                    //Add shift
                    if (! empty($value[3])) {
                        $shift_name = trim($value[3]);
                        $shift = Shift::where('business_id', $business_id)->where('name', $shift_name)->first();
                        if (! empty($shift)) {
                            $temp['essentials_shift_id'] = $shift->id;
                        } else {
                            $is_valid = false;
                            $error_msg = "Shift not found in row no. $row_no";
                            break;
                        }
                    }

                    $temp['clock_in_note'] = ! empty($value[4]) ? mb_substr(trim($value[4]), 0, 2000) : null;
                    $temp['clock_out_note'] = ! empty($value[5]) ? mb_substr(trim($value[5]), 0, 2000) : null;
                    $temp['ip_address'] = ! empty($value[6]) && filter_var(trim($value[6]), FILTER_VALIDATE_IP)
                        ? trim($value[6])
                        : $ip_address;
                    $temp['business_id'] = $business_id;

                    if ($this->attendanceOverlapExists($business_id, (int) $temp['user_id'], $temp['clock_in_time'], $temp['clock_out_time'])) {
                        $is_valid = false;
                        $error_msg = "Attendance overlaps an existing record in row no. $row_no";
                        break;
                    }
                    foreach ($formated_data as $prepared) {
                        if ((int) $prepared['user_id'] === (int) $temp['user_id']
                            && $this->attendanceIntervalsOverlap($prepared['clock_in_time'], $prepared['clock_out_time'], $temp['clock_in_time'], $temp['clock_out_time'])) {
                            $is_valid = false;
                            $error_msg = "Attendance overlaps another imported row in row no. $row_no";
                            break 2;
                        }
                    }
                    $formated_data[] = $temp;
                }

                if (! $is_valid) {
                    throw new \Exception($error_msg);
                }

                if (! empty($formated_data)) {
                    EssentialsAttendance::insert($formated_data);
                }

                $output = ['success' => 1,
                    'msg' => __('product.file_imported_successfully'),
                ];

                DB::commit();
            }
        } catch (ValidationException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect()->back()->with('notification', $output);
        }

        return redirect()->back()->with('status', $output);
    }

    /**
     * Adds attendance row for an employee on add latest attendance form
     *
     * @param  int  $user_id
     * @return Response
     */
    public function getAttendanceRow($user_id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_all_attendance');

        $user = User::forBusiness($business_id)
                    ->findOrFail($user_id);

        $attendance = EssentialsAttendance::where('business_id', $business_id)
                                        ->where('user_id', $user_id)
                                        ->whereNotNull('clock_in_time')
                                        ->whereNull('clock_out_time')
                                        ->first();

        $shifts = Shift::join('essentials_user_shifts as eus', 'eus.essentials_shift_id', '=', 'essentials_shifts.id')
                    ->where('essentials_shifts.business_id', $business_id)
                    ->where('eus.user_id', $user_id)
                    ->where('eus.start_date', '<=', \Carbon::now()->format('Y-m-d'))
                    ->pluck('essentials_shifts.name', 'essentials_shifts.id');

        return view('essentials::attendance.attendance_row')->with(compact('attendance', 'shifts', 'user'));
    }

    private function parseAttendanceDate($value, string $field): string
    {
        try {
            $parsed = $this->moduleUtil->uf_date($value, true);

            return \Carbon::parse($parsed)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                $field => __('validation.date', ['attribute' => $field]),
            ]);
        }
    }

    private function parseImportedAttendanceDate($value, string $field): string
    {
        try {
            if (is_numeric($value)) {
                return \Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                    ->format('Y-m-d H:i:s');
            }

            return \Carbon::parse(trim($value))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'attendance' => __('validation.date', ['attribute' => $field]),
            ]);
        }
    }

    private function validateAttendanceRange(int $business_id, int $user_id, string $clock_in, ?string $clock_out, ?int $ignore_id = null): void
    {
        if (! empty($clock_out) && \Carbon::parse($clock_out)->lessThanOrEqualTo(\Carbon::parse($clock_in))) {
            throw ValidationException::withMessages([
                'clock_out_time' => __('validation.after', [
                    'attribute' => __('essentials::lang.clock_out_time'),
                    'date' => __('essentials::lang.clock_in_time'),
                ]),
            ]);
        }

        if ($this->attendanceOverlapExists($business_id, $user_id, $clock_in, $clock_out, $ignore_id)) {
            throw ValidationException::withMessages([
                'clock_in_time' => __('essentials::lang.attendance_already_exist'),
            ]);
        }
    }

    private function attendanceOverlapExists(int $business_id, int $user_id, string $clock_in, ?string $clock_out, ?int $ignore_id = null): bool
    {
        $query = EssentialsAttendance::where('business_id', $business_id)
            ->where('user_id', $user_id);

        if (! empty($ignore_id)) {
            $query->where('id', '!=', $ignore_id);
        }

        if (! empty($clock_out)) {
            $query->where('clock_in_time', '<', $clock_out);
        }

        return $query->where(function ($query) use ($clock_in) {
            $query->whereNull('clock_out_time')
                ->orWhere('clock_out_time', '>', $clock_in);
        })->exists();
    }

    private function attendanceIntervalsOverlap(string $first_in, ?string $first_out, string $second_in, ?string $second_out): bool
    {
        $first_end = empty($first_out) ? \Carbon::maxValue() : \Carbon::parse($first_out);
        $second_end = empty($second_out) ? \Carbon::maxValue() : \Carbon::parse($second_out);

        return \Carbon::parse($first_in)->lessThan($second_end)
            && \Carbon::parse($second_in)->lessThan($first_end);
    }
}
