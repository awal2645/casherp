<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsUserShift;
use Modules\Essentials\Entities\Shift;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Yajra\DataTables\Facades\DataTables;

class ShiftController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ModuleUtil  $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        if (request()->ajax()) {
            $shifts = Shift::where('essentials_shifts.business_id', $business_id)
                        ->select([
                            'id',
                            'name',
                            'type',
                            'start_time',
                            'end_time',
                            'holidays',
                        ]);

            return Datatables::of($shifts)
                ->editColumn('start_time', function ($row) {
                    $start_time_formated = $this->moduleUtil->format_time($row->start_time);

                    return $start_time_formated;
                })
                ->editColumn('end_time', function ($row) {
                    $end_time_formated = $this->moduleUtil->format_time($row->end_time);

                    return $end_time_formated;
                })
                ->editColumn('type', function ($row) {
                    return __('essentials::lang.'.$row->type);
                })
                ->editColumn('holidays', function ($row) {
                    if (! empty($row->holidays)) {
                        $holidays = array_map(function ($item) {
                            return __('lang_v1.'.$item);
                        }, $row->holidays);

                        return implode(', ', $holidays);
                    }
                })
                ->addColumn('action', function ($row) {
                    $html = '<a href="#" data-href="'.action([\Modules\Essentials\Http\Controllers\ShiftController::class, 'edit'], [$row->id]).'" data-container="#edit_shift_modal" class="btn-modal tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary"><i class="fas fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</a> &nbsp;<a href="#" data-href="'.action([\Modules\Essentials\Http\Controllers\ShiftController::class, 'getAssignUsers'], [$row->id]).'" data-container="#user_shift_modal" class="btn-modal tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent"><i class="fas fa-users" aria-hidden="true"></i> '.__('essentials::lang.assign_users').'</a>';

                    return $html;
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'type'])
                ->make(true);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        $days = $this->moduleUtil->getDays();

        return view('essentials::attendance.shift_modal')->with(compact('days'));
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
        $this->authorizeHrmAction($business_id, []);

        try {
            $input = $this->validateShiftRequest($request, $business_id);

            if ($input['type'] != 'flexible_shift') {
                $input['start_time'] = $this->parseShiftTime($input['start_time'], 'start_time');
                $input['end_time'] = $this->parseShiftTime($input['end_time'], 'end_time');
            } else {
                $input['start_time'] = null;
                $input['end_time'] = null;
            }

            $input['is_allowed_auto_clockout'] = ! empty($input['is_allowed_auto_clockout']) ? 1 : 0;

            if (! empty($input['auto_clockout_time'])) {
                $input['auto_clockout_time'] = $this->parseShiftTime($input['auto_clockout_time'], 'auto_clockout_time');
            } else {
                $input['auto_clockout_time'] = null;
            }

            $input['business_id'] = $business_id;

            Shift::create($input);

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
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        return view('essentials::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);
        $shift = Shift::where('business_id', $business_id)
                    ->findOrFail($id);

        $days = $this->moduleUtil->getDays();

        return view('essentials::attendance.shift_modal')->with(compact('shift', 'days'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        try {
            $input = $this->validateShiftRequest($request, $business_id, $id);

            if ($input['type'] != 'flexible_shift') {
                $input['start_time'] = $this->parseShiftTime($input['start_time'], 'start_time');
                $input['end_time'] = $this->parseShiftTime($input['end_time'], 'end_time');
            } else {
                $input['start_time'] = null;
                $input['end_time'] = null;
            }

            $input['is_allowed_auto_clockout'] = ! empty($input['is_allowed_auto_clockout']) ? 1 : 0;

            if (! empty($input['auto_clockout_time'])) {
                $input['auto_clockout_time'] = $this->parseShiftTime($input['auto_clockout_time'], 'auto_clockout_time');
            } else {
                $input['auto_clockout_time'] = null;
            }

            if (! empty($input['holidays'])) {
                $input['holidays'] = json_encode($input['holidays']);
            } else {
                $input['holidays'] = null;
            }

            $shift = Shift::where('business_id', $business_id)
                        ->where('id', $id)
                        ->update($input);

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
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    public function getAssignUsers($shift_id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);
        $shift = Shift::where('business_id', $business_id)
                    ->with(['user_shifts'])
                    ->findOrFail($shift_id);

        $users = User::forDropdown($business_id, false);

        $user_shifts = [];

        if (! empty($shift->user_shifts)) {
            foreach ($shift->user_shifts as $user_shift) {
                $user_shifts[$user_shift->user_id] = [
                    'start_date' => ! empty($user_shift->start_date) ? $this->moduleUtil->format_date($user_shift->start_date) : null,
                    'end_date' => ! empty($user_shift->end_date) ? $this->moduleUtil->format_date($user_shift->end_date) : null,
                ];
            }
        }

        return view('essentials::attendance.add_shift_users')
                ->with(compact('shift', 'users', 'user_shifts'));
    }

    public function postAssignUsers(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, []);

        try {
            $input = $request->validate([
                'shift_id' => ['required', 'integer'],
                'user_shift' => ['nullable', 'array', 'max:1000'],
                'user_shift.*.is_added' => ['nullable', 'boolean'],
                'user_shift.*.start_date' => ['nullable', 'string', 'max:100'],
                'user_shift.*.end_date' => ['nullable', 'string', 'max:100'],
            ]);
            $shift_id = $input['shift_id'];
            $shift = Shift::where('business_id', $business_id)
                        ->findOrFail($shift_id);

            $user_shifts = $input['user_shift'] ?? [];
            $selected = [];
            foreach ($user_shifts as $key => $value) {
                if (! empty($value['is_added'])) {
                    $user_id = (int) $key;
                    $start_date = $this->parseShiftDate($value['start_date'] ?? null, "user_shift.$key.start_date");
                    $end_date = ! empty($value['end_date'])
                        ? $this->parseShiftDate($value['end_date'], "user_shift.$key.end_date")
                        : null;
                    if (! empty($end_date) && $end_date < $start_date) {
                        throw ValidationException::withMessages([
                            "user_shift.$key.end_date" => __('validation.after_or_equal', ['attribute' => __('essentials::lang.end_date'), 'date' => __('business.start_date')]),
                        ]);
                    }
                    $selected[$user_id] = compact('start_date', 'end_date');
                }
            }

            $user_ids = array_keys($selected);
            if (! empty($user_ids) && User::forBusiness($business_id)->user()->whereIn('id', $user_ids)->count() !== count($user_ids)) {
                throw ValidationException::withMessages([
                    'user_shift' => __('validation.exists', ['attribute' => __('essentials::lang.employee')]),
                ]);
            }

            DB::transaction(function () use ($business_id, $shift_id, $selected, $user_ids) {
                \App\Business::whereKey($business_id)->lockForUpdate()->firstOrFail();
                foreach ($selected as $user_id => $dates) {
                    $overlapQuery = EssentialsUserShift::join('essentials_shifts as shifts', 'shifts.id', '=', 'essentials_user_shifts.essentials_shift_id')
                        ->where('shifts.business_id', $business_id)
                        ->where('essentials_user_shifts.user_id', $user_id)
                        ->where('essentials_user_shifts.essentials_shift_id', '!=', $shift_id)
                        ->where(function ($query) use ($dates) {
                            $query->whereNull('essentials_user_shifts.end_date')
                                ->orWhereDate('essentials_user_shifts.end_date', '>=', $dates['start_date']);
                        });

                    if (! empty($dates['end_date'])) {
                        $overlapQuery->where(function ($query) use ($dates) {
                            $query->whereNull('essentials_user_shifts.start_date')
                                ->orWhereDate('essentials_user_shifts.start_date', '<=', $dates['end_date']);
                        });
                    }

                    $overlap = $overlapQuery->exists();
                    if ($overlap) {
                        throw ValidationException::withMessages([
                            "user_shift.$user_id.start_date" => 'This employee already has another shift during the selected period.',
                        ]);
                    }

                    EssentialsUserShift::updateOrCreate(
                        [
                            'essentials_shift_id' => $shift_id,
                            'user_id' => $user_id,
                        ],
                        $dates
                    );
                }

                $delete_query = EssentialsUserShift::where('essentials_shift_id', $shift_id);
                if (! empty($user_ids)) {
                    $delete_query->whereNotIn('user_id', $user_ids);
                }
                $delete_query->delete();
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

    private function validateShiftRequest(Request $request, int $business_id, ?int $shift_id = null): array
    {
        $days = array_keys($this->moduleUtil->getDays());

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('essentials_shifts', 'name')->where('business_id', $business_id)->ignore($shift_id),
            ],
            'type' => ['required', 'in:fixed_shift,flexible_shift'],
            'start_time' => ['required_unless:type,flexible_shift', 'nullable', 'string', 'max:100'],
            'end_time' => ['required_unless:type,flexible_shift', 'nullable', 'string', 'max:100'],
            'holidays' => ['nullable', 'array', 'max:7'],
            'holidays.*' => ['string', Rule::in($days)],
            'is_allowed_auto_clockout' => ['nullable', 'boolean'],
            'auto_clockout_time' => ['required_if:is_allowed_auto_clockout,1', 'nullable', 'string', 'max:100'],
        ]);
    }

    private function parseShiftTime($value, string $field): string
    {
        try {
            return $this->moduleUtil->uf_time($value);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                $field => __('validation.date_format', ['attribute' => $field, 'format' => 'time']),
            ]);
        }
    }

    private function parseShiftDate($value, string $field): string
    {
        if (empty($value)) {
            throw ValidationException::withMessages([
                $field => __('validation.required', ['attribute' => __('business.start_date')]),
            ]);
        }

        try {
            return \Carbon::parse($this->moduleUtil->uf_date($value))->format('Y-m-d');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                $field => __('validation.date', ['attribute' => $field]),
            ]);
        }
    }
}
