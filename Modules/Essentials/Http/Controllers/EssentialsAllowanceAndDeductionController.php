<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsAllowanceAndDeduction;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Utils\EssentialsUtil;
use Yajra\DataTables\Facades\DataTables;

class EssentialsAllowanceAndDeductionController extends Controller
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
            'essentials.add_allowance_and_deduction',
            'essentials.view_allowance_and_deduction',
        ]);

        if (request()->ajax()) {
            $allowances = EssentialsAllowanceAndDeduction::where('business_id', $business_id)
                ->with(['employees' => fn ($query) => $query->forBusiness($business_id)]);

            return Datatables::of($allowances)
                ->addColumn(
                    'action',
                    function ($row) use ($business_id) {
                        $html = '';
                        if (auth()->user()->canForBusiness('essentials.add_allowance_and_deduction', $business_id)) {
                            $html .= '<button data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsAllowanceAndDeductionController::class, 'edit'], [$row->id]).'" data-container="#add_allowance_deduction_modal" class="btn-modal tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary"><i class="fa fa-edit" aria-hidden="true"></i> '.__('messages.edit').'</button>';

                            $html .= '&nbsp; <button data-href="'.action([\Modules\Essentials\Http\Controllers\EssentialsAllowanceAndDeductionController::class, 'destroy'], [$row->id]).'" class="delete-allowance tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error"><i class="fa fa-trash" aria-hidden="true"></i> '.__('messages.delete').'</button>';
                        }

                        return $html;
                    }
                )
                ->editColumn('applicable_date', function ($row) {
                    return $this->essentialsUtil->format_date($row->applicable_date);
                })
                ->editColumn('type', '{{__("essentials::lang." . $type)}}')
                ->editColumn('amount', '<span class="display_currency" data-currency_symbol="false">{{$amount}}</span> @if($amount_type =="percent") % @endif')
                ->editColumn('employees', function ($row) {
                    $employees = [];
                    foreach ($row->employees as $employee) {
                        $employees[] = $employee->user_full_name;
                    }

                    return implode(', ', $employees);
                })
                ->rawColumns(['action', 'amount'])
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

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        $users = User::forDropdown($business_id, false);

        return view('essentials::allowance_deduction.create')->with(compact('users'));
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

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        try {
            $employeeIds = $this->validatedEmployeeIds($request, $business_id);
            $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'type' => ['required', 'in:allowance,deduction'],
                'amount' => ['required'],
                'amount_type' => ['required', 'in:fixed,percent'],
                'applicable_date' => ['nullable', 'string', 'max:100'],
            ]);

            $input = $request->only(['description', 'type', 'amount', 'amount_type', 'applicable_date']);
            $input['business_id'] = $business_id;
            $input['amount'] = $this->moduleUtil->num_uf($input['amount']);
            if (! is_numeric($input['amount']) || $input['amount'] < 0 || ($input['amount_type'] === 'percent' && $input['amount'] > 100)) {
                throw ValidationException::withMessages([
                    'amount' => $input['amount_type'] === 'percent'
                        ? 'Percentage pay components must be between 0 and 100.'
                        : 'Pay component amounts must be zero or greater.',
                ]);
            }
            $input['applicable_date'] = $this->parseApplicableDate($input['applicable_date'] ?? null);
            DB::transaction(function () use ($input, $employeeIds) {
                $allowance = EssentialsAllowanceAndDeduction::create($input);
                $allowance->employees()->sync($employeeIds);
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
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        $business_id = request()->session()->get('user.business_id');

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        return view('essentials::show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        $allowance = EssentialsAllowanceAndDeduction::where('business_id', $business_id)
                    ->with(['employees' => fn ($query) => $query->forBusiness($business_id)])
                    ->findOrFail($id);
        $users = User::forDropdown($business_id, false);

        $selected_users = [];
        foreach ($allowance->employees as $employee) {
            $selected_users[] = $employee->id;
        }

        $applicable_date = ! empty($allowance->applicable_date) ? $this->essentialsUtil->format_date($allowance->applicable_date) : null;

        return view('essentials::allowance_deduction.edit')
                ->with(compact('allowance', 'users', 'selected_users', 'applicable_date'));
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

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        try {
            $employeeIds = $this->validatedEmployeeIds($request, $business_id);
            $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'type' => ['required', 'in:allowance,deduction'],
                'amount' => ['required'],
                'amount_type' => ['required', 'in:fixed,percent'],
                'applicable_date' => ['nullable', 'string', 'max:100'],
            ]);

            $input = $request->only(['description', 'type', 'amount', 'amount_type', 'applicable_date']);
            $input['amount'] = $this->moduleUtil->num_uf($input['amount']);
            if (! is_numeric($input['amount']) || $input['amount'] < 0 || ($input['amount_type'] === 'percent' && $input['amount'] > 100)) {
                throw ValidationException::withMessages([
                    'amount' => $input['amount_type'] === 'percent'
                        ? 'Percentage pay components must be between 0 and 100.'
                        : 'Pay component amounts must be zero or greater.',
                ]);
            }
            $input['applicable_date'] = $this->parseApplicableDate($input['applicable_date'] ?? null);
            DB::transaction(function () use ($business_id, $id, $input, $employeeIds) {
                $allowance = EssentialsAllowanceAndDeduction::where('business_id', $business_id)
                    ->lockForUpdate()
                    ->findOrFail($id);
                $allowance->update($input);
                $allowance->employees()->sync($employeeIds);
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

        $this->authorizeHrmAction($business_id, 'essentials.add_allowance_and_deduction');

        if (request()->ajax()) {
            try {
                DB::transaction(function () use ($business_id, $id) {
                    $component = EssentialsAllowanceAndDeduction::where('business_id', $business_id)
                        ->lockForUpdate()
                        ->findOrFail($id);
                    $component->employees()->detach();
                    $component->delete();
                });

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
     * Ensure pay components can only be assigned to employees in the active
     * company. Empty assignment is valid and leaves the component unassigned.
     *
     * @return array<int, int>
     */
    private function validatedEmployeeIds(Request $request, int $businessId): array
    {
        $request->validate([
            'employees' => ['nullable', 'array'],
            'employees.*' => ['integer', 'distinct'],
        ]);

        $employeeIds = array_values(array_unique(array_map('intval', (array) $request->input('employees', []))));
        if (empty($employeeIds)) {
            return [];
        }

        $validCount = User::forBusiness($businessId)
            ->whereIn('id', $employeeIds)
            ->count();

        if ($validCount !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employees' => 'One or more selected employees do not belong to the active company.',
            ]);
        }

        return $employeeIds;
    }

    private function parseApplicableDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon::parse($this->essentialsUtil->uf_date($value))->format('Y-m-d');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'applicable_date' => __('validation.date', ['attribute' => __('essentials::lang.applicable_date')]),
            ]);
        }
    }
}
