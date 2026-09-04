<?php

namespace Modules\Essentials\Http\Controllers;

use App\Account;
use App\AccountTransaction;
use App\BusinessLocation;
use App\Category;
use App\Events\TransactionPaymentAdded;
use App\Transaction;
use App\TransactionPayment;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsAllowanceAndDeduction;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\PayrollGroup;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Notifications\PayrollNotification;
use Modules\Essentials\Services\PayrollCalculationService;
use Modules\Essentials\Services\HrmAuditService;
use Modules\Essentials\Utils\EssentialsUtil;
use Yajra\DataTables\Facades\DataTables;

class PayrollController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    protected $essentialsUtil;

    protected $commonUtil;

    protected $transactionUtil;

    protected $businessUtil;

    protected $payrollCalculationService;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil, EssentialsUtil $essentialsUtil, Util $commonUtil, TransactionUtil $transactionUtil, BusinessUtil $businessUtil, PayrollCalculationService $payrollCalculationService)
    {
        $this->moduleUtil = $moduleUtil;
        $this->essentialsUtil = $essentialsUtil;
        $this->commonUtil = $commonUtil;
        $this->transactionUtil = $transactionUtil;
        $this->businessUtil = $businessUtil;
        $this->payrollCalculationService = $payrollCalculationService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $can_view_all_payroll = auth()->user()->can('superadmin')
            || $this->moduleUtil->is_admin(auth()->user(), $business_id)
            || auth()->user()->canForBusiness('essentials.view_all_payroll', $business_id);

        $this->authorizeHrmFeature($business_id);

        if (request()->ajax()) {
            $payrolls = $this->essentialsUtil->getPayrollQuery($business_id);

            if ($can_view_all_payroll) {
                if (! empty(request()->input('user_id'))) {
                    $payrolls->where('transactions.expense_for', request()->input('user_id'));
                }

                if (! empty(request()->input('designation_id'))) {
                    $payrolls->where('dsgn.id', request()->input('designation_id'));
                }

                if (! empty(request()->input('department_id'))) {
                    $payrolls->where('dept.id', request()->input('department_id'));
                }
            }

            if (! $can_view_all_payroll) {
                $payrolls->where('transactions.expense_for', auth()->user()->id);
            }

            if (! empty(request()->input('location_id'))) {
                $payrolls->where('u.location_id', request()->input('location_id'));
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $payrolls->where(function ($q) use ($permitted_locations) {
                    $q->whereIn('epg.location_id', $permitted_locations)
                                ->orWhereNull('epg.location_id');
                });
            }

            if (! empty(request()->month_year)) {
                $month_year_arr = explode('/', request()->month_year);
                if (count($month_year_arr) == 2) {
                    $month = $month_year_arr[0];
                    $year = $month_year_arr[1];

                    $payrolls->whereDate('transaction_date', $year.'-'.$month.'-01');
                }
            }

            return Datatables::of($payrolls)
                ->addColumn(
                    'action',
                    function ($row) {
                        $html = '<div class="btn-group">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" role="menu">';

                        $html .= '<li><a href="#" data-href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'show'], [$row->id]).'" data-container=".view_modal" class="btn-modal"><i class="fa fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';

                        // $html .= '<li><a href="' . action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]) . '" class="view_payment_modal"><i class="fa fa-money"></i> ' . __("purchase.view_payments") . '</a></li>';

                        if (empty($row->payroll_group_id) && $row->payment_status != 'paid' && auth()->user()->canForBusiness('essentials.pay_payroll', $business_id)) {
                            $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fa fa-money"></i> '.__('purchase.add_payment').'</a></li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    }
                )
                ->addColumn('transaction_date', function ($row) {
                    $transaction_date = \Carbon::parse($row->transaction_date);

                    return $transaction_date->format('F Y');
                })
                ->editColumn('final_total', '<span class="display_currency" data-currency_symbol="true">{{$final_total}}</span>')
                ->filterColumn('user', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->editColumn(
                    'payment_status',
                    '<a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, \'show\'], [$id])}}" class="view_payment_modal payment-status-label no-print" data-orig-value="{{$payment_status}}" data-status-name="{{__(\'lang_v1.\' . $payment_status)}}"><span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span></a>
                        <span class="print_section">{{__(\'lang_v1.\' . $payment_status)}}</span>
                        '
                )
                ->removeColumn('id')
                ->rawColumns(['action', 'final_total', 'payment_status'])
                ->make(true);
        }

        $employees = [];
        if (auth()->user()->can('superadmin') || $this->moduleUtil->is_admin(auth()->user(), $business_id) || auth()->user()->canForBusiness('essentials.create_payroll', $business_id)) {
            $employees = $this->__getEmployeesByLocation($business_id);
        }
        $departments = Category::forDropdown($business_id, 'hrm_department');
        $designations = Category::forDropdown($business_id, 'hrm_designation');
        $locations = BusinessLocation::forDropdown($business_id, true, false, true, true);

        return view('essentials::payroll.index')->with(compact('employees', 'departments', 'designations', 'locations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.create_payroll');

        request()->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
            'month_year' => ['required', 'date_format:m/Y'],
            'primary_work_location' => ['nullable', 'integer'],
        ]);

        $employee_ids = array_values(array_unique(array_map('intval', request()->input('employee_ids'))));
        if (User::forBusiness($business_id)->whereIn('id', $employee_ids)->count() !== count($employee_ids)) {
            throw ValidationException::withMessages([
                'employee_ids' => __('validation.exists', ['attribute' => __('essentials::lang.employee')]),
            ]);
        }
        $month_year_arr = explode('/', request()->input('month_year'));
        $location_id = request()->get('primary_work_location');
        if (! empty($location_id) && ! BusinessLocation::where('business_id', $business_id)->whereKey($location_id)->exists()) {
            throw ValidationException::withMessages([
                'primary_work_location' => __('validation.exists', ['attribute' => __('business.location')]),
            ]);
        }
        $month = $month_year_arr[0];
        $year = $month_year_arr[1];

        $transaction_date = $year.'-'.$month.'-01';

        //check if payrolls exists for the month year
        $payrolls = Transaction::where('business_id', $business_id)
                    ->where('type', 'payroll')
                    ->whereIn('expense_for', $employee_ids)
                    ->whereDate('transaction_date', $transaction_date)
                    ->get();

        $add_payroll_for = array_diff($employee_ids, $payrolls->pluck('expense_for')->toArray());

        if (! empty($add_payroll_for)) {
            $location = BusinessLocation::where('business_id', $business_id)
                            ->find($location_id);

            //initialize required data
            $start_date = $transaction_date;
            $end_date = \Carbon::parse($start_date)->lastOfMonth();
            $month_name = $end_date->format('F');

            $employees = User::forBusiness($business_id)
                            ->find($add_payroll_for);

            $payrolls = [];
            foreach ($employees as $employee) {

                $employmentProfile = EmploymentProfile::forBusiness($business_id)
                    ->where('user_id', $employee->id)
                    ->first();
                $compensation = (array) optional($employmentProfile)->compensation;

                //get employee info
                $payrolls[$employee->id]['name'] = $employee->user_full_name;
                $payrolls[$employee->id]['essentials_salary'] = $compensation['amount'] ?? $employee->essentials_salary;
                $payrolls[$employee->id]['essentials_pay_period'] = $compensation['pay_period'] ?? ($compensation['basis'] ?? $employee->essentials_pay_period);
                $payrolls[$employee->id]['total_leaves'] = $this->essentialsUtil->getTotalLeavesForGivenDateOfAnEmployee($business_id, $employee->id, $start_date, $end_date->format('Y-m-d'));
                $payrolls[$employee->id]['total_days_worked'] = $this->essentialsUtil->getTotalDaysWorkedForGivenDateOfAnEmployee($business_id, $employee->id, $start_date, $end_date);

                //get total work duration of employee(attendance)
                $payrolls[$employee->id]['total_work_duration'] = $this->essentialsUtil->getTotalWorkDuration('hour', $employee->id, $business_id, $start_date, $end_date->format('Y-m-d'));

                //get total earned commission for employee
                $business_details = $this->businessUtil->getDetails($business_id);
                $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

                $commsn_calculation_type = empty($pos_settings['cmmsn_calculation_type']) || $pos_settings['cmmsn_calculation_type'] == 'invoice_value' ? 'invoice_value' : $pos_settings['cmmsn_calculation_type'];

                $total_commission = 0;
                if ($commsn_calculation_type == 'payment_received') {
                    $payment_details = $this->transactionUtil->getTotalPaymentWithCommission($business_id, $start_date, $end_date, null, $employee->id);
                    //Get Commision
                    $total_commission = $employee->cmmsn_percent * $payment_details['total_payment_with_commission'] / 100;
                } else {
                    $sell_details = $this->transactionUtil->getTotalSellCommission($business_id, $start_date, $end_date, null, $employee->id);
                    $total_commission = $employee->cmmsn_percent * $sell_details['total_sales_with_commission'] / 100;
                }

                if ($total_commission > 0) {
                    $payrolls[$employee->id]['allowances']['allowance_names'][] = __('essentials::lang.sale_commission');
                    $payrolls[$employee->id]['allowances']['allowance_amounts'][] = $total_commission;
                    $payrolls[$employee->id]['allowances']['allowance_types'][] = 'fixed';
                    $payrolls[$employee->id]['allowances']['allowance_percents'][] = 0;
                }
                $settings = $this->essentialsUtil->getEssentialsSettings();
                //get total sales added by the employee
                $sale_totals = $this->transactionUtil->getUserTotalSales($business_id, $employee->id, $start_date, $end_date);

                $total_sales = ! empty($settings['calculate_sales_target_commission_without_tax']) && $settings['calculate_sales_target_commission_without_tax'] == 1 ? $sale_totals['total_sales_without_tax'] : $sale_totals['total_sales'];

                //get sales target if exists
                $sales_target = EssentialsUserSalesTarget::where('user_id', $employee->id)
                                                    ->where('target_start', '<=', $total_sales)
                                                    ->where('target_end', '>=', $total_sales)
                                                    ->first();

                $total_sales_target_commission_percent = ! empty($sales_target) ? $sales_target->commission_percent : 0;

                $total_sales_target_commission = $this->transactionUtil->calc_percentage($total_sales, $total_sales_target_commission_percent);

                if ($total_sales_target_commission > 0) {
                    $payrolls[$employee->id]['allowances']['allowance_names'][] = __('essentials::lang.sales_target_commission');
                    $payrolls[$employee->id]['allowances']['allowance_amounts'][] = $total_sales_target_commission;
                    $payrolls[$employee->id]['allowances']['allowance_types'][] = 'fixed';
                    $payrolls[$employee->id]['allowances']['allowance_percents'][] = 0;
                }

                //get earnings & deductions of employee
                $allowances_and_deductions = $this->essentialsUtil->getEmployeeAllowancesAndDeductions($business_id, $employee->id, $start_date, $end_date);
                foreach ($allowances_and_deductions as $ad) {
                    if ($ad->type == 'allowance') {
                        $payrolls[$employee->id]['allowances']['allowance_names'][] = $ad->description;
                        $payrolls[$employee->id]['allowances']['allowance_amounts'][] = $ad->amount_type == 'fixed' ? $ad->amount : 0;
                        $payrolls[$employee->id]['allowances']['allowance_types'][] = $ad->amount_type;
                        $payrolls[$employee->id]['allowances']['allowance_percents'][] = $ad->amount_type == 'percent' ? $ad->amount : 0;
                    } else {
                        $payrolls[$employee->id]['deductions']['deduction_names'][] = $ad->description;
                        $payrolls[$employee->id]['deductions']['deduction_amounts'][] = $ad->amount_type == 'fixed' ? $ad->amount : 0;
                        $payrolls[$employee->id]['deductions']['deduction_types'][] = $ad->amount_type;
                        $payrolls[$employee->id]['deductions']['deduction_percents'][] = $ad->amount_type == 'percent' ? $ad->amount : 0;
                    }
                }
            }

            $action = 'create';

            return view('essentials::payroll.create')
                    ->with(compact('month_name', 'transaction_date', 'year', 'payrolls', 'action', 'location'));
        } else {
            return redirect()->action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])
                ->with('status',
                    [
                        'success' => true,
                        'msg' => __('essentials::lang.payroll_already_added_for_given_user'),
                    ]
                );
        }
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
        $this->authorizeHrmAction($business_id, 'essentials.create_payroll');

        try {
            $validated = $this->validatePayrollGroupRequest($request, false);
            $payrolls = $validated['payrolls'];
            $employee_ids = $this->validatedPayrollEmployeeIds($business_id, $payrolls);
            $this->validatePayrollLocation($business_id, $validated['location_id'] ?? null);
            $settings = $request->session()->get('business.essentials_settings');
            $settings = ! empty($settings) ? json_decode($settings, true) : [];
            $prefix = ! empty($settings['payroll_ref_no_prefix']) ? $settings['payroll_ref_no_prefix'] : '';
            $notify_employee = ! empty($validated['notify_employee']);

            $transactions_to_notify = DB::transaction(function () use ($business_id, $validated, $payrolls, $employee_ids, $prefix, $notify_employee) {
                // Serialize payroll creation for the company so duplicate month/employee
                // checks remain reliable even when two managers submit together.
                \App\Business::whereKey($business_id)->lockForUpdate()->firstOrFail();

                $duplicates = Transaction::where('business_id', $business_id)
                    ->where('type', 'payroll')
                    ->whereIn('expense_for', $employee_ids)
                    ->whereDate('transaction_date', $validated['transaction_date'])
                    ->lockForUpdate()
                    ->pluck('expense_for')
                    ->all();
                if (! empty($duplicates)) {
                    throw ValidationException::withMessages([
                        'payrolls' => __('essentials::lang.payroll_already_added_for_given_user'),
                    ]);
                }

                $calculated_payrolls = [];
                $gross_total = 0.0;
                foreach ($payrolls as $employee_id => $payroll) {
                    $calculated = $this->payrollCalculationService->calculate($payroll);
                    $calculated_payrolls[(int) $employee_id] = $calculated;
                    $gross_total = round($gross_total + $calculated['final_total'], 4);
                }

                $payroll_group = PayrollGroup::create([
                    'business_id' => $business_id,
                    'name' => $validated['payroll_group_name'],
                    'status' => $validated['payroll_group_status'],
                    'gross_total' => $gross_total,
                    'location_id' => $validated['location_id'] ?? null,
                    'created_by' => auth()->user()->id,
                ]);

                $transaction_ids = [];
                $notify = [];
                foreach ($calculated_payrolls as $employee_id => $calculated) {
                    $ref_count = $this->moduleUtil->setAndGetReferenceCount('payroll');
                    $transaction = Transaction::create(array_merge($calculated, [
                        'transaction_date' => $validated['transaction_date'],
                        'business_id' => $business_id,
                        'created_by' => auth()->user()->id,
                        'expense_for' => $employee_id,
                        'type' => 'payroll',
                        'payment_status' => 'due',
                        'status' => 'final',
                        'ref_no' => $this->moduleUtil->generateReferenceNumber('payroll', $ref_count, null, $prefix),
                    ]));
                    $transaction_ids[] = $transaction->id;
                    if ($notify_employee && $payroll_group->status === 'final') {
                        $notify[] = $transaction;
                    }
                }

                $payroll_group->payrollGroupTransactions()->sync($transaction_ids);

                return $notify;
            });

            foreach ($transactions_to_notify as $transaction) {
                try {
                    $transaction->action = 'created';
                    $transaction->transaction_for->notify(new PayrollNotification($transaction));
                } catch (\Throwable $notification_error) {
                    \Log::warning('Payroll saved but notification failed: '.$notification_error->getMessage());
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

        return redirect()->action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])->with('status', $output);
    }

    private function getAllowanceAndDeductionJson($payroll)
    {
        $allowance_names = $payroll['allowance_names'];
        $allowance_types = $payroll['allowance_types'];
        $allowance_percents = $payroll['allowance_percent'];
        $allowance_names_array = [];
        $allowance_percent_array = [];
        $allowance_amounts = [];

        foreach ($payroll['allowance_amounts'] as $key => $value) {
            if (! empty($allowance_names[$key])) {
                $allowance_amounts[] = $this->moduleUtil->num_uf($value);
                $allowance_names_array[] = $allowance_names[$key];
                $allowance_percent_array[] = ! empty($allowance_percents[$key]) ? $this->moduleUtil->num_uf($allowance_percents[$key]) : 0;
            }
        }

        $deduction_names = $payroll['deduction_names'];
        $deduction_types = $payroll['deduction_types'];
        $deduction_percents = $payroll['deduction_percent'];
        $deduction_names_array = [];
        $deduction_percents_array = [];
        $deduction_amounts = [];
        foreach ($payroll['deduction_amounts'] as $key => $value) {
            if (! empty($deduction_names[$key])) {
                $deduction_names_array[] = $deduction_names[$key];
                $deduction_amounts[] = $this->moduleUtil->num_uf($value);
                $deduction_percents_array[] = ! empty($deduction_percents[$key]) ? $this->moduleUtil->num_uf($deduction_percents[$key]) : 0;
            }
        }

        $output['essentials_allowances'] = json_encode([
            'allowance_names' => $allowance_names_array,
            'allowance_amounts' => $allowance_amounts,
            'allowance_types' => $allowance_types,
            'allowance_percents' => $allowance_percent_array,
        ]);
        $output['essentials_deductions'] = json_encode([
            'deduction_names' => $deduction_names_array,
            'deduction_amounts' => $deduction_amounts,
            'deduction_types' => $deduction_types,
            'deduction_percents' => $deduction_percents_array,
        ]);

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmFeature($business_id);

        $query = Transaction::where('business_id', $business_id)
                        ->where('type', 'payroll')
                        ->with(['transaction_for', 'payment_lines']);

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);
        if (! auth()->user()->can('superadmin') && ! $is_admin && ! auth()->user()->canForBusiness('essentials.view_all_payroll', $business_id)) {
            $query->where('expense_for', auth()->user()->id);
        }
        $payroll = $query->findOrFail($id);

        $transaction_date = \Carbon::parse($payroll->transaction_date);

        $department = Category::where('business_id', $business_id)
                        ->where('category_type', 'hrm_department')
                        ->find($payroll->transaction_for->essentials_department_id);

        $designation = Category::where('business_id', $business_id)
                        ->where('category_type', 'hrm_designation')
                        ->find($payroll->transaction_for->essentials_designation_id);

        $location = BusinessLocation::where('business_id', $business_id)
                        ->find($payroll->transaction_for->location_id);

        $month_name = $transaction_date->format('F');
        $year = $transaction_date->format('Y');
        $allowances = ! empty($payroll->essentials_allowances) ? json_decode($payroll->essentials_allowances, true) : [];
        $deductions = ! empty($payroll->essentials_deductions) ? json_decode($payroll->essentials_deductions, true) : [];
        $bank_details = $this->bankDetailsForPayroll($business_id, (int) $payroll->expense_for, 'Payslip viewed');
        $payment_types = $this->moduleUtil->payment_types();
        $final_total_in_words = $this->commonUtil->numToWord($payroll->final_total, auth()->user()->language);

        $start_of_month = \Carbon::parse($payroll->transaction_date);
        $end_of_month = \Carbon::parse($payroll->transaction_date)->endOfMonth();

        $leaves = EssentialsLeave::where('business_id', $business_id)
                        ->where('user_id', $payroll->transaction_for->id)
                        ->where('status', 'approved')
                        ->whereDate('start_date', '<=', $end_of_month)
                        ->whereDate('end_date', '>=', $start_of_month)
                        ->get();

        $total_leaves = 0;
        $days_in_a_month = \Carbon::parse($start_of_month)->daysInMonth;
        foreach ($leaves as $key => $leave) {
            $start_date = \Carbon::parse($leave->start_date)->max($start_of_month);
            $end_date = \Carbon::parse($leave->end_date)->min($end_of_month);

            $diff = $start_date->diffInDays($end_date);
            $diff += 1;
            $total_leaves += $diff;
        }

        $total_days_present = $this->essentialsUtil->getTotalDaysWorkedForGivenDateOfAnEmployee(
            $business_id,
            $payroll->transaction_for->id,
            $start_of_month->format('Y-m-d'),
            $end_of_month->format('Y-m-d')
        );

        $total_work_duration = $this->essentialsUtil->getTotalWorkDuration('hour',
        $payroll->transaction_for->id, $business_id, $start_of_month->format('Y-m-d'),
        $end_of_month->format('Y-m-d'));

        return view('essentials::payroll.show')
        ->with(compact('payroll', 'month_name', 'allowances', 'deductions', 'year', 'payment_types',
        'bank_details', 'designation', 'department', 'final_total_in_words', 'total_leaves', 'days_in_a_month',
        'total_work_duration', 'location', 'total_days_present'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.update_payroll');

        $payroll = Transaction::where('business_id', $business_id)
                                ->with(['transaction_for'])
                                ->where('type', 'payroll')
                                ->findOrFail($id);

        $transaction_date = \Carbon::parse($payroll->transaction_date);
        $month_name = $transaction_date->format('F');
        $year = $transaction_date->format('Y');
        $allowances = ! empty($payroll->essentials_allowances) ? json_decode($payroll->essentials_allowances, true) : [];
        $deductions = ! empty($payroll->essentials_deductions) ? json_decode($payroll->essentials_deductions, true) : [];

        return view('essentials::payroll.edit')->with(compact('payroll', 'month_name', 'allowances', 'deductions', 'year'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.update_payroll');

        try {
            $validated = $this->validatePayrollItemRequest($request);
            $calculated = $this->payrollCalculationService->calculate($validated);

            $payroll = DB::transaction(function () use ($business_id, $id, $calculated) {
                $payroll = Transaction::where('business_id', $business_id)
                    ->where('type', 'payroll')
                    ->lockForUpdate()
                    ->findOrFail($id);
                if (TransactionPayment::where('transaction_id', $payroll->id)->exists()) {
                    throw ValidationException::withMessages([
                        'payroll' => 'A payroll with recorded payments cannot be recalculated.',
                    ]);
                }

                $payroll->update($calculated);

                $group = PayrollGroup::where('business_id', $business_id)
                    ->whereHas('payrollGroupTransactions', fn ($query) => $query->where('transactions.id', $payroll->id))
                    ->lockForUpdate()
                    ->first();
                if (! empty($group)) {
                    $group->gross_total = $group->payrollGroupTransactions()->sum('final_total');
                    $group->save();
                }

                return $payroll;
            });

            try {
                $payroll->action = 'updated';
                $payroll->transaction_for->notify(new PayrollNotification($payroll));
            } catch (\Throwable $notification_error) {
                \Log::warning('Payroll updated but notification failed: '.$notification_error->getMessage());
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

        return redirect()->action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.delete_payroll');

        if (request()->ajax()) {
            try {
                $payroll_group = PayrollGroup::where('business_id', $business_id)
                            ->with(['payrollGroupTransactions'])
                            ->findOrFail($id);

                DB::beginTransaction();
                if ($payroll_group->status == 'draft') {
                    $transaction_ids = $payroll_group->payrollGroupTransactions->pluck('id')->toArray();
                    //delete all account tranactions
                    AccountTransaction::whereIn('transaction_id', $transaction_ids)->delete();
                    //delete all transaction payments
                    TransactionPayment::whereIn('transaction_id', $transaction_ids)->delete();

                    $payroll_group->payrollGroupTransactions()->delete();
                    $payroll_group->delete();
                }

                DB::commit();
                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getAllowanceAndDeductionRow(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, ['essentials.create_payroll', 'essentials.update_payroll']);

        if ($request->ajax()) {
            $input = $request->validate([
                'employee_id' => ['required', 'integer'],
                'type' => ['required', 'in:allowance,deduction'],
            ]);
            User::forBusiness($business_id)->findOrFail($input['employee_id']);
            $employee = $input['employee_id'];
            $type = $input['type'];

            $ad_row = view('essentials::payroll.allowance_and_deduction_row')
                        ->with(compact('type', 'employee'))
                        ->render();

            return $ad_row;
        }
    }

    public function payrollGroupDatatable(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.view_all_payroll');

        if ($request->ajax()) {
            $payroll_groups = PayrollGroup::where('essentials_payroll_groups.business_id', $business_id)
                                ->join('users as u', 'u.id', '=', 'essentials_payroll_groups.created_by')
                                ->leftJoin('business_locations as BL', 'essentials_payroll_groups.location_id', '=', 'BL.id')
                                ->select('essentials_payroll_groups.id as id', 'essentials_payroll_groups.name as name', 'essentials_payroll_groups.status as status', 'essentials_payroll_groups.created_at as created_at',
                                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by"), 'essentials_payroll_groups.payment_status as payment_status', 'essentials_payroll_groups.gross_total as gross_total',
                                    'BL.name as location_name'
                                );

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $payroll_groups->where(function ($q) use ($permitted_locations) {
                    $q->whereIn('essentials_payroll_groups.location_id', $permitted_locations)
                                ->orWhereNull('essentials_payroll_groups.location_id');
                });
            }

            return Datatables::of($payroll_groups)
                ->addColumn(
                    'action',
                    function ($row) {
                        $html = '<div class="btn-group">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" role="menu">';

                        $html .= '<li>
                                    <a href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'viewPayrollGroup'], [$row->id]).'" target="_blank">
                                            <i class="fa fa-eye" aria-hidden="true"></i> '
                                            .__('messages.view').
                                    '</a>
                                </li>';
                        if (auth()->user()->canForBusiness('essentials.update_payroll', $business_id)) {
                            $html .= '<li>
                                        <a href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'getEditPayrollGroup'], [$row->id]).'" target="_blank">
                                                <i class="fas fa-edit" aria-hidden="true"></i> '
                                                .__('messages.edit').
                                        '</a>
                                    </li>';
                        }

                        if (auth()->user()->canForBusiness('essentials.delete_payroll', $business_id) && $row->status == 'draft') {
                            $html .= '<li><a href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'destroy'], [$row->id]).'" class="delete-payroll"><i class="fa fa-trash" aria-hidden="true"></i> '.__('messages.delete').'</a></li>';
                        }

                        if ($row->status == 'final' && $row->payment_status != 'paid' && auth()->user()->canForBusiness('essentials.pay_payroll', $business_id)) {
                            $html .= '<li>
                                    <a href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'addPayment'], [$row->id]).'" target="_blank">
                                            <i class="fas fa-money-check" aria-hidden="true"></i> '
                                            .__('purchase.add_payment').
                                    '</a>
                                </li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    }
                )
                ->editColumn('status', '
                    @lang("sale.".$status)
                ')
                ->editColumn('created_at', '
                    {{@format_datetime($created_at)}}
                ')
                ->editColumn('gross_total', '
                    @format_currency($gross_total)
                ')
                ->editColumn('location_name', '
                    @if(!empty($location_name))
                        {{$location_name}}
                    @else
                        {{__("report.all_locations")}}
                    @endif
                ')
                ->editColumn(
                    'payment_status',
                    '<span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span>
                        '
                )
                ->filterColumn('added_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'added_by', 'created_at', 'status', 'gross_total', 'payment_status', 'location_name'])
                ->make(true);
        }
    }

    public function viewPayrollGroup($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.view_all_payroll');

        $payroll_group = PayrollGroup::where('business_id', $business_id)
                            ->with(['payrollGroupTransactions', 'payrollGroupTransactions.transaction_for', 'businessLocation', 'business'])
                            ->findOrFail($id);

        $payrolls = [];
        $month_name = null;
        $year = null;
        foreach ($payroll_group->payrollGroupTransactions as $transaction) {

            //payroll info
            if (empty($month_name) && empty($year)) {
                $transaction_date = \Carbon::parse($transaction->transaction_date);
                $month_name = $transaction_date->format('F');
                $year = $transaction_date->format('Y');
            }

            //transaction info
            $payrolls[$transaction->expense_for]['transaction_id'] = $transaction->id;
            $payrolls[$transaction->expense_for]['final_total'] = $transaction->final_total;
            $payrolls[$transaction->expense_for]['payment_status'] = $transaction->payment_status;

            //get employee info
            $payrolls[$transaction->expense_for]['employee'] = $transaction->transaction_for->user_full_name;
            $payrolls[$transaction->expense_for]['bank_details'] = $this->bankDetailsForPayroll(
                $business_id,
                (int) $transaction->expense_for,
                'Payroll group reviewed'
            );
        }

        return view('essentials::payroll.view_payroll_group')
            ->with(compact('payroll_group', 'month_name', 'year', 'payrolls'));
    }

    public function getEditPayrollGroup($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.update_payroll');

        $payroll_group = PayrollGroup::where('business_id', $business_id)
                            ->with(['payrollGroupTransactions', 'payrollGroupTransactions.transaction_for', 'businessLocation'])
                            ->findOrFail($id);

        //payroll location
        $location = $payroll_group->businessLocation;

        $payrolls = [];
        $transaction_date = null;
        $month_name = null;
        $year = null;
        foreach ($payroll_group->payrollGroupTransactions as $transaction) {

            //payroll info
            if (empty($transaction_date) && empty($month_name) && empty($year)) {
                $transaction_date = \Carbon::parse($transaction->transaction_date);
                $month_name = $transaction_date->format('F');
                $year = $transaction_date->format('Y');
                $start_date = \Carbon::parse($transaction->transaction_date);
                $end_date = \Carbon::parse($start_date)->lastOfMonth();
            }
            //transaction info
            $payrolls[$transaction->expense_for]['transaction_id'] = $transaction->id;

            //get employee info
            $payrolls[$transaction->expense_for]['name'] = $transaction->transaction_for->user_full_name ?? '';
            $payrolls[$transaction->expense_for]['staff_note'] = $transaction->staff_note;
            $payrolls[$transaction->expense_for]['essentials_amount_per_unit_duration'] = $transaction->essentials_amount_per_unit_duration;
            $payrolls[$transaction->expense_for]['essentials_duration'] = $transaction->essentials_duration;
            $payrolls[$transaction->expense_for]['essentials_duration_unit'] = $transaction->essentials_duration_unit;
            $payrolls[$transaction->expense_for]['total_leaves'] = $this->essentialsUtil->getTotalLeavesForGivenDateOfAnEmployee($business_id, $transaction->expense_for, $start_date->format('Y-m-d'), $end_date->format('Y-m-d'));
            $payrolls[$transaction->expense_for]['total_days_worked'] = $this->essentialsUtil->getTotalDaysWorkedForGivenDateOfAnEmployee($business_id, $transaction->expense_for, $start_date, $end_date);

            //get total work duration of employee(attendance)
            $payrolls[$transaction->expense_for]['total_work_duration'] = $this->essentialsUtil->getTotalWorkDuration('hour', $transaction->expense_for, $business_id, $start_date->format('Y-m-d'), $end_date->format('Y-m-d'));

            //get earnings employee
            $allowances = ! empty($transaction->essentials_allowances) ? json_decode($transaction->essentials_allowances, true) : [];

            if (empty($allowances['allowance_names']) && empty($allowances['allowance_amounts'])) {
                $allowances['allowance_names'][] = '';
                $allowances['allowance_amounts'][] = 0;
                $allowances['allowance_types'][] = 'fixed';
                $allowances['allowance_percents'][] = '';
            }
            $payrolls[$transaction->expense_for]['allowances'] = $allowances;

            //get deductions of employee
            $deductions = ! empty($transaction->essentials_deductions) ? json_decode($transaction->essentials_deductions, true) : [];

            if (empty($deductions['deduction_names']) && empty($deductions['deduction_amounts'])) {
                $deductions['deduction_names'][] = '';
                $deductions['deduction_amounts'][] = 0;
                $deductions['deduction_types'][] = 'fixed';
                $deductions['deduction_percents'][] = '';
            }

            $payrolls[$transaction->expense_for]['deductions'] = $deductions;
        }

        $action = 'edit';

        return view('essentials::payroll.create')
            ->with(compact('month_name', 'transaction_date', 'year', 'payrolls', 'payroll_group', 'action', 'location'));
    }

    public function getUpdatePayrollGroup(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.update_payroll');

        try {
            $validated = $this->validatePayrollGroupRequest($request, true);
            $payrolls = $validated['payrolls'];
            $this->validatedPayrollEmployeeIds($business_id, $payrolls);
            $notify_employee = ! empty($validated['notify_employee']);

            $transactions_to_notify = DB::transaction(function () use ($business_id, $validated, $payrolls, $notify_employee) {
                \App\Business::whereKey($business_id)->lockForUpdate()->firstOrFail();
                $payroll_group = PayrollGroup::where('business_id', $business_id)
                    ->lockForUpdate()
                    ->findOrFail($validated['payroll_group_id']);
                $group_transactions = $payroll_group->payrollGroupTransactions()
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'payroll')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($group_transactions->count() !== count($payrolls)) {
                    throw ValidationException::withMessages([
                        'payrolls' => 'Every payroll in the group must be submitted exactly once.',
                    ]);
                }

                $gross_total = 0.0;
                $notify = [];
                foreach ($payrolls as $employee_id => $payload) {
                    $transaction_id = (int) $payload['transaction_id'];
                    $payroll = $group_transactions->get($transaction_id);
                    if (empty($payroll) || (int) $payroll->expense_for !== (int) $employee_id) {
                        throw ValidationException::withMessages([
                            'payrolls' => 'A submitted payroll does not belong to this employee or payroll group.',
                        ]);
                    }
                    if (TransactionPayment::where('transaction_id', $payroll->id)->exists()) {
                        throw ValidationException::withMessages([
                            'payrolls' => 'A payroll with recorded payments cannot be recalculated.',
                        ]);
                    }

                    $calculated = $this->payrollCalculationService->calculate($payload);
                    $payroll->update($calculated);
                    $gross_total = round($gross_total + $calculated['final_total'], 4);
                    if ($notify_employee && $validated['payroll_group_status'] === 'final') {
                        $notify[] = $payroll;
                    }
                }

                $payroll_group->update([
                    'name' => $validated['payroll_group_name'],
                    'status' => $validated['payroll_group_status'],
                    'gross_total' => $gross_total,
                ]);

                return $notify;
            });

            foreach ($transactions_to_notify as $payroll) {
                try {
                    $payroll->action = 'updated';
                    $payroll->transaction_for->notify(new PayrollNotification($payroll));
                } catch (\Throwable $notification_error) {
                    \Log::warning('Payroll updated but notification failed: '.$notification_error->getMessage());
                }
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

        return redirect()->action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])->with('status', $output);
    }

    public function addPayment($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.pay_payroll');

        $payroll_group = PayrollGroup::where('business_id', $business_id)
                            ->with(['payrollGroupTransactions', 'payrollGroupTransactions.transaction_for', 'businessLocation', 'business'])
                            ->findOrFail($id);
        abort_unless($payroll_group->status === 'final', 422, 'Only final payroll groups can be paid.');

        $payrolls = [];
        $month_name = null;
        $year = null;
        foreach ($payroll_group->payrollGroupTransactions as $transaction) {

            //payroll info
            if (empty($month_name) && empty($year)) {
                $transaction_date = \Carbon::parse($transaction->transaction_date);
                $month_name = $transaction_date->format('F');
                $year = $transaction_date->format('Y');
            }

            //transaction info
            $paid_amount = $this->transactionUtil->getTotalPaid($transaction->id);
            $pending_amount = $transaction->final_total - $paid_amount;

            if ($pending_amount < 0) {
                $pending_amount = 0;
            }

            $payrolls[$transaction->expense_for]['amount'] = $pending_amount;
            $payrolls[$transaction->expense_for]['amount_formated'] = $this->transactionUtil->num_f($pending_amount);
            $payrolls[$transaction->expense_for]['payments'] = TransactionPayment::where('transaction_id', $transaction->id)->get();

            $payrolls[$transaction->expense_for]['transaction_id'] = $transaction->id;
            $payrolls[$transaction->expense_for]['final_total'] = $transaction->final_total;
            $payrolls[$transaction->expense_for]['payment_status'] = $transaction->payment_status;
            $payrolls[$transaction->expense_for]['paid_on'] = \Carbon::now();

            //get employee info
            $payrolls[$transaction->expense_for]['employee'] = $transaction->transaction_for->user_full_name;
            $payrolls[$transaction->expense_for]['employee_id'] = $transaction->transaction_for->id;
            $payrolls[$transaction->expense_for]['bank_details'] = $this->bankDetailsForPayroll(
                $business_id,
                (int) $transaction->expense_for,
                'Payroll payment prepared'
            );
        }

        $payment_types = $this->transactionUtil->payment_types();
        $accounts = $this->moduleUtil->accountsDropdown($business_id, true, false, true);

        return view('essentials::payroll.pay_payroll_group')
            ->with(compact('payroll_group', 'month_name', 'year', 'payrolls', 'payment_types', 'accounts'));
    }

    public function postAddPayment(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.pay_payroll');

        try {
            $validated = $request->validate([
                'payroll_group_id' => ['required', 'integer'],
                'payments' => ['required', 'array', 'min:1'],
                'payments.*.transaction_id' => ['required', 'integer'],
                'payments.*.final_total' => ['nullable', 'string', 'max:50'],
                'payments.*.method' => ['nullable', 'string', 'max:50'],
                'payments.*.paid_on' => ['nullable', 'string', 'max:100'],
                'payments.*.payment_note' => ['nullable', 'string', 'max:2000'],
                'payments.*.account_id' => ['nullable', 'integer'],
                'payments.*.cheque_number' => ['nullable', 'string', 'max:191'],
                'payments.*.bank_account_number' => ['nullable', 'string', 'max:191'],
                'payments.*.card_transaction_number' => ['nullable', 'string', 'max:191'],
                'payments.*.card_type' => ['nullable', 'string', 'max:50'],
                'payments.*.transaction_no_1' => ['nullable', 'string', 'max:191'],
                'payments.*.transaction_no_2' => ['nullable', 'string', 'max:191'],
                'payments.*.transaction_no_3' => ['nullable', 'string', 'max:191'],
            ]);
            $payment_methods = array_keys($this->transactionUtil->payment_types());

            DB::transaction(function () use ($business_id, $validated, $payment_methods) {
                $payroll_group = PayrollGroup::where('business_id', $business_id)
                    ->lockForUpdate()
                    ->findOrFail($validated['payroll_group_id']);
                if ($payroll_group->status !== 'final') {
                    throw ValidationException::withMessages([
                        'payroll_group_id' => 'Only final payroll groups can be paid.',
                    ]);
                }

                $group_transaction_ids = $payroll_group->payrollGroupTransactions()
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'payroll')
                    ->pluck('transactions.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $processed = 0;

                foreach ($validated['payments'] as $employee_id => $payment) {
                    $amount = $this->transactionUtil->num_uf($payment['final_total'] ?? 0);
                    if (! is_numeric($amount) || (float) $amount <= 0) {
                        continue;
                    }
                    if (empty($payment['method']) || ! in_array($payment['method'], $payment_methods, true)) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.method" => __('validation.in', ['attribute' => __('purchase.payment_method')]),
                        ]);
                    }
                    if (empty($payment['paid_on'])) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.paid_on" => __('validation.required', ['attribute' => __('lang_v1.paid_on')]),
                        ]);
                    }

                    $transaction_id = (int) $payment['transaction_id'];
                    if (! in_array($transaction_id, $group_transaction_ids, true)) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.transaction_id" => 'The payroll does not belong to this payroll group.',
                        ]);
                    }

                    $transaction = Transaction::where('business_id', $business_id)
                        ->where('type', 'payroll')
                        ->lockForUpdate()
                        ->findOrFail($transaction_id);
                    if ((int) $transaction->expense_for !== (int) $employee_id) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.transaction_id" => 'The payroll does not belong to this employee.',
                        ]);
                    }

                    $paid_amount = TransactionPayment::where('transaction_id', $transaction->id)
                        ->lockForUpdate()
                        ->get()
                        ->sum('amount');
                    $pending_amount = round((float) $transaction->final_total - (float) $paid_amount, 4);
                    if ($transaction->payment_status === 'paid' || (float) $amount > $pending_amount + 0.0001) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.final_total" => __('lang_v1.max_amount_to_be_paid_is', [
                                'amount' => $this->transactionUtil->num_f(max(0, $pending_amount)),
                            ]),
                        ]);
                    }

                    $account_id = $payment['account_id'] ?? null;
                    if (! empty($account_id) && $payment['method'] !== 'advance'
                        && ! Account::where('business_id', $business_id)->notClosed()->whereKey($account_id)->exists()) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.account_id" => __('validation.exists', ['attribute' => __('lang_v1.payment_account')]),
                        ]);
                    }

                    $paid_on = $this->transactionUtil->uf_date($payment['paid_on'], true);
                    try {
                        $paid_on = \Carbon::parse($paid_on)->format('Y-m-d H:i:s');
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            "payments.$employee_id.paid_on" => __('validation.date', ['attribute' => __('lang_v1.paid_on')]),
                        ]);
                    }

                    $transaction_before = $transaction->replicate();
                    $input = [
                        'method' => $payment['method'],
                        'note' => $payment['payment_note'] ?? null,
                        'business_id' => $business_id,
                        'paid_on' => $paid_on,
                        'transaction_id' => $transaction->id,
                        'amount' => round((float) $amount, 4),
                        'created_by' => auth()->user()->id,
                        'cheque_number' => $payment['cheque_number'] ?? null,
                        'bank_account_number' => $payment['bank_account_number'] ?? null,
                        'card_transaction_number' => $payment['card_transaction_number'] ?? null,
                        'card_type' => $payment['card_type'] ?? null,
                    ];
                    if (! empty($account_id) && $payment['method'] !== 'advance') {
                        $input['account_id'] = $account_id;
                    }
                    if (in_array($input['method'], ['custom_pay_1', 'custom_pay_2', 'custom_pay_3'], true)) {
                        $suffix = substr($input['method'], -1);
                        $input['transaction_no'] = $payment['transaction_no_'.$suffix] ?? null;
                    }

                    $ref_count = $this->transactionUtil->setAndGetReferenceCount('purchase_payment');
                    $input['payment_ref_no'] = $this->transactionUtil->generateReferenceNumber('purchase_payment', $ref_count);
                    $transaction_payment = TransactionPayment::create($input);
                    $event_input = array_merge($input, ['transaction_type' => $transaction->type]);
                    event(new TransactionPaymentAdded($transaction_payment, $event_input));

                    $payment_status = $this->transactionUtil->updatePaymentStatus($transaction->id);
                    $transaction->payment_status = $payment_status;
                    $this->transactionUtil->activityLog($transaction, 'payment_edited', $transaction_before);
                    $processed++;
                }

                if ($processed === 0) {
                    throw ValidationException::withMessages([
                        'payments' => 'Enter at least one positive payroll payment.',
                    ]);
                }

                $this->_updatePayrollGroupPaymentStatus($payroll_group->id, $business_id);
            });

            $output = ['success' => true,
                'msg' => __('purchase.payment_added_success'),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])->with('status', $output);
    }

    protected function _updatePayrollGroupPaymentStatus($payroll_group_id, $business_id)
    {
        $payroll_group = PayrollGroup::where('business_id', $business_id)
                            ->with(['payrollGroupTransactions'])
                            ->findOrFail($payroll_group_id);

        $total_transaction = count($payroll_group->payrollGroupTransactions);
        $total_paid = $payroll_group->payrollGroupTransactions->where('payment_status', 'paid')->count();
        $total_due = $payroll_group->payrollGroupTransactions->where('payment_status', '=', 'due')->count();

        if ($total_transaction == $total_paid) {
            $payment_status = 'paid';
        } elseif ($total_transaction == $total_due) {
            $payment_status = 'due';
        } else {
            $payment_status = 'partial';
        }

        $payroll_group->payment_status = $payment_status;
        $payroll_group->save();
    }

    /**
     * List payrolls & pay components
     * of an user
     *
     * @return Response
     */
    public function getMyPayrolls(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmFeature($business_id);

        if ($request->ajax()) {
            $payrolls = $this->essentialsUtil->getPayrollQuery($business_id);

            $payrolls->where('transactions.expense_for', auth()->user()->id);

            return Datatables::of($payrolls)
                ->addColumn(
                    'action',
                    function ($row) {
                        $html = '<a href="#" data-href="'.action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'show'], [$row->id]).'" data-container=".view_modal" class="btn-modal btn-info btn btn-sm">
                            <i class="fa fa-eye" aria-hidden="true"></i> '
                            .__('messages.view').
                            '</a>';

                        return $html;
                    }
                )
                ->addColumn('transaction_date', function ($row) {
                    $transaction_date = \Carbon::parse($row->transaction_date);

                    return $transaction_date->format('F Y');
                })
                ->editColumn('final_total', '<span class="display_currency" data-currency_symbol="true">{{$final_total}}</span>')
                ->editColumn(
                    'payment_status',
                    '<span class="label @payment_status($payment_status)">{{__(\'lang_v1.\' . $payment_status)}}
                        </span>'
                )
                ->removeColumn('id')
                ->rawColumns(['action', 'final_total', 'payment_status'])
                ->make(true);
        }

        $pay_components = EssentialsAllowanceAndDeduction::join('essentials_user_allowance_and_deductions as EUAD', 'essentials_allowances_and_deductions.id', '=', 'EUAD.allowance_deduction_id')
                ->where('essentials_allowances_and_deductions.business_id', $business_id)
                ->where('EUAD.user_id', auth()->user()->id)
                ->get();

        return view('essentials::payroll.partials.user_payrolls')
            ->with(compact('pay_components'));
    }

    public function getEmployeesBasedOnLocation(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.create_payroll');

        try {
            $input = $request->validate(['location_id' => ['nullable', 'integer']]);
            $location_id = $input['location_id'] ?? null;
            $this->validatePayrollLocation($business_id, $location_id);

            $employees = $this->__getEmployeesByLocation($business_id, $location_id);

            //dynamically generate dropdown
            $employees_html = view('essentials::payroll.partials.employee_dropdown')
                                ->with(compact('employees'))
                                ->render();
            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
                'employees_html' => $employees_html,
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    private function validatePayrollGroupRequest(Request $request, bool $updating): array
    {
        $rules = [
            'transaction_date' => ['required', 'date_format:Y-m-d'],
            'payroll_group_name' => ['required', 'string', 'max:191'],
            'payroll_group_status' => ['required', 'in:draft,final'],
            'location_id' => ['nullable', 'integer'],
            'notify_employee' => ['nullable', 'boolean'],
            'payrolls' => ['required', 'array', 'min:1', 'max:1000'],
            'payrolls.*.expense_for' => ['required', 'integer'],
            'payrolls.*.essentials_duration' => ['required'],
            'payrolls.*.essentials_duration_unit' => ['nullable', 'string', 'max:20'],
            'payrolls.*.essentials_amount_per_unit_duration' => ['required'],
            'payrolls.*.staff_note' => ['nullable', 'string', 'max:2000'],
            'payrolls.*.allowance_names' => ['nullable', 'array', 'max:100'],
            'payrolls.*.allowance_names.*' => ['nullable', 'string', 'max:255'],
            'payrolls.*.allowance_types' => ['nullable', 'array', 'max:100'],
            'payrolls.*.allowance_types.*' => ['nullable', 'in:fixed,percent'],
            'payrolls.*.allowance_percent' => ['nullable', 'array', 'max:100'],
            'payrolls.*.allowance_percent.*' => ['nullable'],
            'payrolls.*.allowance_amounts' => ['nullable', 'array', 'max:100'],
            'payrolls.*.allowance_amounts.*' => ['nullable'],
            'payrolls.*.deduction_names' => ['nullable', 'array', 'max:100'],
            'payrolls.*.deduction_names.*' => ['nullable', 'string', 'max:255'],
            'payrolls.*.deduction_types' => ['nullable', 'array', 'max:100'],
            'payrolls.*.deduction_types.*' => ['nullable', 'in:fixed,percent'],
            'payrolls.*.deduction_percent' => ['nullable', 'array', 'max:100'],
            'payrolls.*.deduction_percent.*' => ['nullable'],
            'payrolls.*.deduction_amounts' => ['nullable', 'array', 'max:100'],
            'payrolls.*.deduction_amounts.*' => ['nullable'],
        ];
        if ($updating) {
            $rules['payroll_group_id'] = ['required', 'integer'];
            $rules['payrolls.*.transaction_id'] = ['required', 'integer'];
        }

        return $request->validate($rules);
    }

    private function validatePayrollItemRequest(Request $request): array
    {
        return $request->validate([
            'essentials_duration' => ['required'],
            'essentials_duration_unit' => ['nullable', 'string', 'max:20'],
            'essentials_amount_per_unit_duration' => ['required'],
            'staff_note' => ['nullable', 'string', 'max:2000'],
            'allowance_names' => ['nullable', 'array', 'max:100'],
            'allowance_names.*' => ['nullable', 'string', 'max:255'],
            'allowance_types' => ['nullable', 'array', 'max:100'],
            'allowance_types.*' => ['nullable', 'in:fixed,percent'],
            'allowance_percent' => ['nullable', 'array', 'max:100'],
            'allowance_percent.*' => ['nullable'],
            'allowance_amounts' => ['nullable', 'array', 'max:100'],
            'allowance_amounts.*' => ['nullable'],
            'deduction_names' => ['nullable', 'array', 'max:100'],
            'deduction_names.*' => ['nullable', 'string', 'max:255'],
            'deduction_types' => ['nullable', 'array', 'max:100'],
            'deduction_types.*' => ['nullable', 'in:fixed,percent'],
            'deduction_percent' => ['nullable', 'array', 'max:100'],
            'deduction_percent.*' => ['nullable'],
            'deduction_amounts' => ['nullable', 'array', 'max:100'],
            'deduction_amounts.*' => ['nullable'],
        ]);
    }

    private function validatedPayrollEmployeeIds(int $business_id, array $payrolls): array
    {
        $employee_ids = [];
        foreach ($payrolls as $employee_id => $payroll) {
            $employee_id = (int) $employee_id;
            if ($employee_id <= 0 || $employee_id !== (int) ($payroll['expense_for'] ?? 0)) {
                throw ValidationException::withMessages([
                    'payrolls' => 'Each payroll must match its employee.',
                ]);
            }
            $employee_ids[] = $employee_id;
        }
        $employee_ids = array_values(array_unique($employee_ids));
        if (User::forBusiness($business_id)->user()->whereIn('id', $employee_ids)->count() !== count($employee_ids)) {
            throw ValidationException::withMessages([
                'payrolls' => __('validation.exists', ['attribute' => __('essentials::lang.employee')]),
            ]);
        }

        return $employee_ids;
    }

    private function validatePayrollLocation(int $business_id, $location_id): void
    {
        if (! empty($location_id) && ! BusinessLocation::where('business_id', $business_id)->whereKey($location_id)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => __('validation.exists', ['attribute' => __('business.location')]),
            ]);
        }
    }

    private function bankDetailsForPayroll(int $businessId, int $employeeId, string $purpose): array
    {
        $profile = EmploymentProfile::forBusiness($businessId)
            ->where('user_id', $employeeId)
            ->first();
        if (empty($profile)) {
            $legacy = User::forBusiness($businessId)->find($employeeId);

            return ! empty($legacy->bank_details) ? (array) json_decode($legacy->bank_details, true) : [];
        }

        $canView = auth()->id() === $employeeId
            || auth()->user()->canForBusiness('essentials.view_employee_bank', $businessId)
            || auth()->user()->canForBusiness('essentials.pay_payroll', $businessId);
        if (! $canView) {
            return $profile->maskedBankDetails();
        }

        app(HrmAuditService::class)->recordSensitiveAccess($profile, 'bank_details', $purpose);

        return (array) $profile->bank_details;
    }

    private function __getEmployeesByLocation($business_id, $location_id = null)
    {
        $query = User::forBusiness($business_id)
                    ->user();

        if (! empty($location_id)) {
            $query->where(function ($query) use ($business_id, $location_id) {
                $query->whereExists(function ($subquery) use ($business_id, $location_id) {
                        $subquery->select(DB::raw(1))
                            ->from('hrm_employment_profiles as hep')
                            ->whereColumn('hep.user_id', 'users.id')
                            ->where('hep.business_id', $business_id)
                            ->where('hep.location_id', $location_id)
                            ->whereNull('hep.deleted_at');
                    })
                    ->orWhere(function ($legacy) use ($business_id, $location_id) {
                        $legacy->where('users.location_id', $location_id)
                            ->whereNotExists(function ($subquery) use ($business_id) {
                                $subquery->select(DB::raw(1))
                                    ->from('hrm_employment_profiles as hep')
                                    ->whereColumn('hep.user_id', 'users.id')
                                    ->where('hep.business_id', $business_id)
                                    ->whereNull('hep.deleted_at');
                            });
                    });
            });
        } else {
            $query->where(function ($query) use ($business_id) {
                $query->whereExists(function ($subquery) use ($business_id) {
                        $subquery->select(DB::raw(1))
                            ->from('hrm_employment_profiles as hep')
                            ->whereColumn('hep.user_id', 'users.id')
                            ->where('hep.business_id', $business_id)
                            ->whereNull('hep.location_id')
                            ->whereNull('hep.deleted_at');
                    })
                    ->orWhere(function ($legacy) use ($business_id) {
                        $legacy->whereNull('users.location_id')
                            ->whereNotExists(function ($subquery) use ($business_id) {
                                $subquery->select(DB::raw(1))
                                    ->from('hrm_employment_profiles as hep')
                                    ->whereColumn('hep.user_id', 'users.id')
                                    ->where('hep.business_id', $business_id)
                                    ->whereNull('hep.deleted_at');
                            });
                    });
            });
        }

        $users = $query->select('id', DB::raw("CONCAT(COALESCE(surname, ''),' ',COALESCE(first_name, ''),' ',COALESCE(last_name,'')) as full_name"))->get();

        $employees = $users->pluck('full_name', 'id')->toArray();

        return $employees;
    }
}
