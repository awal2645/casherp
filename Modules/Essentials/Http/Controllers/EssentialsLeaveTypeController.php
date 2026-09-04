<?php

namespace Modules\Essentials\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Yajra\DataTables\Facades\DataTables;

class EssentialsLeaveTypeController extends Controller
{
    use AuthorizesHrmRequests;

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
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
        $this->authorizeHrmAction($business_id, 'essentials.crud_leave_type');

        if (request()->ajax()) {
            $leave_types = EssentialsLeaveType::where('business_id', $business_id)
                        ->select(['leave_type', 'max_leave_count', 'id']);

            return Datatables::of($leave_types)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'\Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>'
                )
                ->removeColumn('id')
                ->rawColumns([2])
                ->make(false);
        }

        return view('essentials::leave_type.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.crud_leave_type');

        return view('essentials::leave_type.create');
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
        $this->authorizeHrmAction($business_id, 'essentials.crud_leave_type');

        try {
            $input = $request->validate([
                'leave_type' => [
                    'required',
                    'string',
                    'max:191',
                    Rule::unique('essentials_leave_types', 'leave_type')->where('business_id', $business_id),
                ],
                'max_leave_count' => ['nullable', 'integer', 'min:1', 'max:3660'],
                'leave_count_interval' => ['nullable', 'in:month,year'],
            ]);

            $input['business_id'] = $business_id;

            EssentialsLeaveType::create($input);
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
        $this->authorizeHrmAction($business_id, 'essentials.crud_leave_type');

        $leave_type = EssentialsLeaveType::where('business_id', $business_id)
                                        ->findOrFail($id);

        return view('essentials::leave_type.edit')->with(compact('leave_type'));
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
        $this->authorizeHrmAction($business_id, 'essentials.crud_leave_type');

        try {
            $input = $request->validate([
                'leave_type' => [
                    'required',
                    'string',
                    'max:191',
                    Rule::unique('essentials_leave_types', 'leave_type')
                        ->where('business_id', $business_id)
                        ->ignore($id),
                ],
                'max_leave_count' => ['nullable', 'integer', 'min:1', 'max:3660'],
                'leave_count_interval' => ['nullable', 'in:month,year'],
            ]);

            $input['business_id'] = $business_id;

            EssentialsLeaveType::where('business_id', $business_id)
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
     * @return Response
     */
    public function destroy()
    {
    }
}
