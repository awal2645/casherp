<?php

namespace Modules\Essentials\Utils;

use App\Transaction;
use App\User;
use App\Utils\Util;
use DB;
use Illuminate\Support\Facades\View;
use Modules\Essentials\Entities\EssentialsAllowanceAndDeduction;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsUserShift;
use Modules\Essentials\Entities\Shift;
use Modules\Essentials\Entities\EssentialsHoliday;


class EssentialsUtil extends Util
{
    /**
     * Function to calculate total work duration of a user for a period of time
     *
     * @param  string  $unit
     * @param  int  $user_id
     * @param  int  $business_id
     * @param  int  $start_date = null
     * @param  int  $end_date = null
     */
    public function getTotalWorkDuration(
        $unit,
        $user_id,
        $business_id,
        $start_date = null,
        $end_date = null
    ) {
        $total_work_duration = 0;
        if ($unit == 'hour') {
            $query = EssentialsAttendance::where('business_id', $business_id)
                                        ->where('user_id', $user_id)
                                        ->whereNotNull('clock_out_time');

            if (! empty($start_date) && ! empty($end_date)) {
                $query->whereDate('clock_in_time', '>=', $start_date)
                            ->whereDate('clock_in_time', '<=', $end_date);
            }

            $minutes_sum = $query->select(DB::raw('SUM(TIMESTAMPDIFF(MINUTE, clock_in_time, clock_out_time)) as total_minutes'))->first();
            $total_work_duration = ! empty($minutes_sum->total_minutes) ? $minutes_sum->total_minutes / 60 : 0;
        }

        return number_format($total_work_duration, 2);
    }

    /**
     * Parses month and year from date
     *
     * @param  string  $month_year
     */
    public function getDateFromMonthYear($month_year)
    {
        $month_year_arr = explode('/', $month_year);
        $month = $month_year_arr[0];
        $year = $month_year_arr[1];

        $transaction_date = $year.'-'.$month.'-01';

        return $transaction_date;
    }

    /**
     * Retrieves all allowances and deductions of an employeee
     *
     * @param  int  $business_id
     * @param  int  $user_id
     * @param  string  $start_date = null
     * @param  string  $end_date = null
     */
    public function getEmployeeAllowancesAndDeductions($business_id, $user_id, $start_date = null, $end_date = null)
    {
        $query = EssentialsAllowanceAndDeduction::join('essentials_user_allowance_and_deductions as euad', 'euad.allowance_deduction_id', '=', 'essentials_allowances_and_deductions.id')
                ->where('business_id', $business_id)
                ->where('euad.user_id', $user_id);

        //Filter if applicable one
        if (! empty($start_date) && ! empty($end_date)) {
            $query->where(function ($q) use ($start_date, $end_date) {
                $q->whereNull('applicable_date')
                    ->orWhereBetween('applicable_date', [$start_date, $end_date]);
            });
        }
        $allowances_and_deductions = $query->get();

        return $allowances_and_deductions;
    }

    /**
     * Validates user clock in and returns available shift id
     */
    public function checkUserShift($business_id, $user_id, $settings, $clock_in_time = null)
    {
        $shift_id = null;
        $clock_in_datetime = ! empty($clock_in_time) ? \Carbon::parse($clock_in_time) : \Carbon::now();
        $clock_in_date = $clock_in_datetime->format('Y-m-d');
        $clock_in_time = $clock_in_datetime->format('H:i');
        
        $day_string = strtolower($clock_in_datetime->format('l'));
        $grace_before_checkin = ! empty($settings['grace_before_checkin']) ? (int) $settings['grace_before_checkin'] : 0;
        $grace_after_checkin = ! empty($settings['grace_after_checkin']) ? (int) $settings['grace_after_checkin'] : 0;
        
        //$clock_in_start = ! empty($clock_in_time) ? \Carbon::parse($clock_in_time)->subMinutes($grace_before_checkin) : \Carbon::now()->subMinutes($grace_before_checkin);
        //$clock_in_end = ! empty($clock_in_time) ? \Carbon::parse($clock_in_time)->addMinutes($grace_after_checkin) : \Carbon::now()->addMinutes($grace_after_checkin);

        $user_shifts = EssentialsUserShift::join('essentials_shifts as s', 's.id', '=', 'essentials_user_shifts.essentials_shift_id')
                    ->where('s.business_id', $business_id)
                    ->where('user_id', $user_id)
                    ->where('start_date', '<=', $clock_in_date)
                    ->where(function ($q) use ($clock_in_date) {
                        $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $clock_in_date);
                    })
                    ->select('essentials_user_shifts.*', 's.holidays', 's.start_time', 's.end_time', 's.type')
                    ->get();

                    
        foreach ($user_shifts as $shift) {
            $holidays = json_decode($shift->holidays, true);
            //check if holiday
            if (is_array($holidays) && in_array($day_string, $holidays)) {
                continue;
            }

            //Check allocated shift time
            if (! empty($shift->start_time)) {

                $start_start_time = \Carbon::parse($shift->start_time)->subMinutes($grace_before_checkin);
                $start_end_time = \Carbon::parse($shift->start_time)->addMinutes($grace_after_checkin);

                if(\Carbon::parse($clock_in_time)->between($start_start_time, $start_end_time)){
                    return $shift->essentials_shift_id;
                }
            }

            if ($shift->type == 'flexible_shift') {
                return $shift->essentials_shift_id;
            }
        }

        return $shift_id;
    }

    /**
     * Validates user clock out
     */
    public function canClockOut($clock_in, $settings, $clock_out_time = null)
    {
        $shift = Shift::where('business_id', $clock_in->business_id)
            ->find($clock_in->essentials_shift_id);
        if (empty($shift) || empty($shift->end_time)) {
            return true;
        }
        $grace_before_checkout = ! empty($settings['grace_before_checkout']) ? (int) $settings['grace_before_checkout'] : 0;
        $grace_after_checkout = ! empty($settings['grace_after_checkout']) ? (int) $settings['grace_after_checkout'] : 0;

        if ($shift->type != 'flexible_shift') {
            $clock_in_at = \Carbon::parse($clock_in->clock_in_time);
            $shift_start = \Carbon::parse($clock_in_at->format('Y-m-d').' '.$shift->start_time);
            $shift_end = \Carbon::parse($clock_in_at->format('Y-m-d').' '.$shift->end_time);
            if ($shift_end->lessThanOrEqualTo($shift_start)) {
                $shift_end->addDay();
            }

            $end_start_time = $shift_end->copy()->subMinutes($grace_before_checkout);
            $end_end_time = $shift_end->copy()->addMinutes($grace_after_checkout);

            if (\Carbon::parse($clock_out_time)->between($end_start_time, $end_end_time)) {
                return true;
            }
        } elseif ($shift->type == 'flexible_shift') {
            return true;
        } else {
            return false;
        }
    }

    public function clockin($data, $essentials_settings)
    {
        //Check user can clockin
        $clock_in_time = is_object($data['clock_in_time']) ? $data['clock_in_time']->toDateTimeString() : $data['clock_in_time'];

        $shift = $this->checkUserShift($data['business_id'], $data['user_id'], $essentials_settings, $clock_in_time);

        if (empty($shift)) {
            $available_shifts = $this->getAllAvailableShiftsForGivenUser($data['business_id'], $data['user_id']);

            $available_shifts_html = view('essentials::attendance.avail_shifts')
                                        ->with(compact('available_shifts'))
                                        ->render();

            $output = ['success' => false,
                'msg' => __('essentials::lang.shift_not_allocated'),
                'type' => 'clock_in',
                'shift_details' => $available_shifts_html,
            ];

            return $output;
        }

        $data['essentials_shift_id'] = $shift;

        $output = DB::transaction(function () use ($data, $shift) {
            User::forBusiness($data['business_id'])
                ->whereKey($data['user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $already_clocked_in = EssentialsAttendance::where('business_id', $data['business_id'])
                ->where('user_id', $data['user_id'])
                ->whereNull('clock_out_time')
                ->exists();
            if ($already_clocked_in) {
                return ['success' => false,
                    'msg' => __('essentials::lang.already_clocked_in'),
                    'type' => 'clock_in',
                ];
            }

            EssentialsAttendance::create($data);

            $shift_info = Shift::getGivenShiftInfo($data['business_id'], $shift);
            $current_shift_html = view('essentials::attendance.current_shift')
                ->with(compact('shift_info'))
                ->render();

            return ['success' => true,
                'msg' => __('essentials::lang.clock_in_success'),
                'type' => 'clock_in',
                'current_shift' => $current_shift_html,
            ];
        });

        return $output;
    }

    public function clockout($data, $essentials_settings)
    {
        $clock_out_time = is_object($data['clock_out_time']) ? $data['clock_out_time']->toDateTimeString() : $data['clock_out_time'];

        $output = DB::transaction(function () use ($data, $essentials_settings, $clock_out_time) {
            User::forBusiness($data['business_id'])
                ->whereKey($data['user_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $clock_in = EssentialsAttendance::where('business_id', $data['business_id'])
                ->where('user_id', $data['user_id'])
                ->whereNull('clock_out_time')
                ->lockForUpdate()
                ->first();
            if (empty($clock_in)) {
                return ['success' => false,
                    'msg' => __('essentials::lang.not_clocked_in'),
                    'type' => 'clock_out',
                ];
            }

            if (! $this->canClockOut($clock_in, $essentials_settings, $clock_out_time)) {
                return ['success' => false,
                    'msg' => __('essentials::lang.shift_not_over'),
                    'type' => 'clock_out',
                ];
            }

            $clock_in->clock_out_time = $data['clock_out_time'];
            $clock_in->clock_out_note = $data['clock_out_note'];
            $clock_in->clock_out_location = $data['clock_out_location'] ?? '';
            $clock_in->save();

            return ['success' => true,
                'msg' => __('essentials::lang.clock_out_success'),
                'type' => 'clock_out',
            ];
        });

        return $output;
    }

    public function getAllAvailableShiftsForGivenUser($business_id, $user_id)
    {
        $available_user_shifts = EssentialsUserShift::join('essentials_shifts as s', 's.id', '=',
                                    'essentials_user_shifts.essentials_shift_id')
                                    ->where('user_id', $user_id)
                                    ->where('s.business_id', $business_id)
                                    ->whereDate('start_date', '<=', \Carbon::today())
                                    ->where(function ($query) {
                                        $query->whereNull('end_date')
                                            ->orWhereDate('end_date', '>=', \Carbon::today());
                                    })
                                    ->select('essentials_user_shifts.start_date', 'essentials_user_shifts.end_date',
                                        's.name', 's.type', 's.start_time', 's.end_time', 's.holidays')
                                    ->get();

        return $available_user_shifts;
    }

    /**
     * get total leaves of and employee for given date
     *
     * @param  int  $business_id
     * @param  int  $employee_id
     * @param  string  $start_date
     * @param  string  $end_date
     */
    public function getTotalLeavesForGivenDateOfAnEmployee($business_id, $employee_id, $start_date, $end_date)
    {
        $period_start = \Carbon::parse($start_date)->startOfDay();
        $period_end = \Carbon::parse($end_date)->endOfDay();
        $leaves = EssentialsLeave::where('business_id', $business_id)
                        ->where('user_id', $employee_id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $period_end->toDateString())
                        ->whereDate('end_date', '>=', $period_start->toDateString())
                        ->get();

        $total_leaves = 0;
        foreach ($leaves as $key => $leave) {
            $leave_start = \Carbon::parse($leave->start_date)->startOfDay();
            $leave_end = \Carbon::parse($leave->end_date)->startOfDay();
            $effective_start = $leave_start->greaterThan($period_start) ? $leave_start : $period_start;
            $effective_end = $leave_end->lessThan($period_end) ? $leave_end : $period_end;

            $diff = $effective_start->diffInDays($effective_end);
            $diff += 1;
            $total_leaves += $diff;
        }

        return $total_leaves;
    }

    public function getTotalDaysWorkedForGivenDateOfAnEmployee($business_id, $employee_id, $start_date, $end_date)
    {
        $attendances = EssentialsAttendance::where('business_id', $business_id)
                        ->where('user_id', $employee_id)
                        ->whereNotNull('clock_out_time')
                        ->whereDate('clock_in_time', '>=', $start_date)
                        ->whereDate('clock_in_time', '<=', $end_date)
                        ->get()
                        ->groupBy(function ($attendance, $key) {
                            return \Carbon::parse($attendance->clock_in_time)->format('Y-m-d');
                        });

        return count($attendances);
    }

    public function getPayrollQuery($business_id)
    {
        $payrolls = Transaction::where('transactions.business_id', $business_id)
                    ->where('type', 'payroll')
                    ->join('users as u', 'u.id', '=', 'transactions.expense_for')
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
                    ->leftJoin('hrm_employment_profiles as payroll_profile', function ($join) use ($business_id) {
                        $join->on('payroll_profile.user_id', '=', 'u.id')
                            ->where('payroll_profile.business_id', '=', $business_id)
                            ->whereNull('payroll_profile.deleted_at');
                    })
                    ->leftJoin('categories as dept', function ($join) use ($business_id) {
                        $join->on('dept.id', '=', DB::raw('COALESCE(payroll_profile.department_id, u.essentials_department_id)'))
                            ->where('dept.business_id', '=', $business_id);
                    })
                    ->leftJoin('categories as dsgn', function ($join) use ($business_id) {
                        $join->on('dsgn.id', '=', DB::raw('COALESCE(payroll_profile.designation_id, u.essentials_designation_id)'))
                            ->where('dsgn.business_id', '=', $business_id);
                    })
                    ->leftJoin('essentials_payroll_group_transactions as epgt', 'transactions.id', '=', 'epgt.transaction_id')
                    ->leftJoin('essentials_payroll_groups as epg', 'epgt.payroll_group_id', '=', 'epg.id')
                    ->select([
                        'transactions.id',
                        DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user"),
                        'final_total',
                        'transaction_date',
                        'ref_no',
                        'transactions.payment_status',
                        'dept.name as department',
                        'dsgn.name as designation',
                        'epgt.payroll_group_id',
                    ]);

        return $payrolls;
    }

    public function getEssentialsSettings()
    {
        $settings = request()->session()->get('business.essentials_settings');
        $settings = ! empty($settings) ? json_decode($settings, true) : [];

        return $settings;
    }

    public function Gettotalholiday($business_id, $location, $start_date, $end_date, $permitted_locations){
        $holidays = EssentialsHoliday::where('essentials_holidays.business_id', $business_id)
                        ->leftJoin('business_locations as bl', function ($join) use ($business_id) {
                            $join->on('bl.id', '=', 'essentials_holidays.location_id')
                                ->where('bl.business_id', '=', $business_id);
                        })
                        ->select([
                            'essentials_holidays.id',
                            'essentials_holidays.name',
                            'bl.name as location',
                            'start_date',
                            'end_date',
                            'note',
                        ]);

            if ($permitted_locations != 'all') {
                $holidays->where(function ($query) use ($permitted_locations) {
                    $query->whereIn('essentials_holidays.location_id', $permitted_locations)
                        ->orWhereNull('essentials_holidays.location_id');
                });
            }

            if (! empty($location)) {
                $holidays->where('essentials_holidays.location_id', $location);
            }

            if (! empty($start_date) && ! empty($end_date)) {
                $holidays->whereDate('essentials_holidays.start_date', '>=', $start_date)
                            ->whereDate('essentials_holidays.start_date', '<=', $end_date);
            }

            return $holidays;
    }
}
