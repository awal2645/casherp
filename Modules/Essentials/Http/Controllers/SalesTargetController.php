<?php

namespace Modules\Essentials\Http\Controllers;

use App\User;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EssentialsUserSalesTarget;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Yajra\DataTables\Facades\DataTables;

class SalesTargetController extends Controller
{
    use AuthorizesHrmRequests;

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
        $this->authorizeHrmAction($business_id, 'essentials.access_sales_target');

        if (request()->ajax()) {
            $users = User::forBusiness($business_id)
                        ->user()
                        ->where('allow_login', 1)
                        ->select(['id',
                            DB::raw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name"), ]);

            return Datatables::of($users)
                ->addColumn(
                    'action',
                    '<button type="button" data-href="{{action(\'\Modules\Essentials\Http\Controllers\SalesTargetController@setSalesTarget\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container="#set_sales_target_modal"><i class="fas fa-bullseye"></i> @lang("essentials::lang.set_sales_target")</button>'
                )
                ->filterColumn('full_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->whereRaw("CONCAT(COALESCE(surname, ''), ' ', COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$keyword}%"])
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                    });
                })
                ->removeColumn('id')
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('essentials::sales_targets.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function setSalesTarget($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.access_sales_target');

        $user = User::forBusiness($business_id)
                    ->user()
                    ->findOrFail($id);

        $sales_targets = EssentialsUserSalesTarget::where('user_id', $id)
                                                ->get();

        return view('essentials::sales_targets.sales_target_modal')->with(compact('user', 'sales_targets'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function saveSalesTarget(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $this->authorizeHrmAction($business_id, 'essentials.access_sales_target');

        try {
            $input = $request->validate([
                'user_id' => ['required', 'integer'],
                'edit_target' => ['nullable', 'array', 'max:100'],
                'edit_target.*.target_start' => ['required'],
                'edit_target.*.target_end' => ['required'],
                'edit_target.*.commission_percent' => ['required'],
                'sales_amount_start' => ['nullable', 'array', 'max:100'],
                'sales_amount_start.*' => ['nullable'],
                'sales_amount_end' => ['nullable', 'array', 'max:100'],
                'sales_amount_end.*' => ['nullable'],
                'commission' => ['nullable', 'array', 'max:100'],
                'commission.*' => ['nullable'],
            ]);
            $user = User::forBusiness($business_id)->user()->findOrFail($input['user_id']);

            $rows = [];
            foreach (($input['edit_target'] ?? []) as $target_id => $value) {
                $rows[] = $this->normalizeSalesTarget($value, (int) $target_id);
            }
            $starts = $input['sales_amount_start'] ?? [];
            $ends = $input['sales_amount_end'] ?? [];
            $commissions = $input['commission'] ?? [];
            foreach ($starts as $key => $value) {
                $target_start = $this->moduleUtil->num_uf($value);
                $target_end = $this->moduleUtil->num_uf($ends[$key] ?? null);
                if ((float) $target_start === 0.0 && (float) $target_end === 0.0) {
                    continue;
                }
                $rows[] = $this->normalizeSalesTarget([
                    'target_start' => $value,
                    'target_end' => $ends[$key] ?? null,
                    'commission_percent' => $commissions[$key] ?? null,
                ]);
            }

            usort($rows, fn ($first, $second) => $first['target_start'] <=> $second['target_start']);
            foreach ($rows as $index => $row) {
                if ($index > 0 && $row['target_start'] <= $rows[$index - 1]['target_end']) {
                    throw ValidationException::withMessages([
                        'sales_amount_start' => 'Sales target ranges cannot overlap or share the same boundary.',
                    ]);
                }
            }

            DB::transaction(function () use ($business_id, $user, $rows) {
                \App\Business::whereKey($business_id)->lockForUpdate()->firstOrFail();
                $existing = EssentialsUserSalesTarget::where('user_id', $user->id)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $target_ids = [];
                foreach ($rows as $row) {
                    $target_id = $row['id'] ?? null;
                    unset($row['id']);
                    if (! empty($target_id)) {
                        if (! $existing->has($target_id)) {
                            throw ValidationException::withMessages([
                                'edit_target' => 'A sales target does not belong to this employee.',
                            ]);
                        }
                        $existing->get($target_id)->update($row);
                        $target_ids[] = $target_id;
                    } else {
                        $row['user_id'] = $user->id;
                        $target_ids[] = EssentialsUserSalesTarget::create($row)->id;
                    }
                }

                $delete_query = EssentialsUserSalesTarget::where('user_id', $user->id);
                if (! empty($target_ids)) {
                    $delete_query->whereNotIn('id', $target_ids);
                }
                $delete_query->delete();
            });

            $output = [
                'success' => true,
                'msg' => __('lang_v1.success'),
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return back()->with('status', $output);
    }

    private function normalizeSalesTarget(array $value, ?int $target_id = null): array
    {
        $target_start = $this->moduleUtil->num_uf($value['target_start'] ?? null);
        $target_end = $this->moduleUtil->num_uf($value['target_end'] ?? null);
        $commission_percent = $this->moduleUtil->num_uf($value['commission_percent'] ?? null);

        if (! is_numeric($target_start) || ! is_numeric($target_end) || ! is_numeric($commission_percent)) {
            throw ValidationException::withMessages([
                'sales_amount_start' => 'Sales targets and commission must be numeric.',
            ]);
        }
        if ((float) $target_start < 0 || (float) $target_end < (float) $target_start) {
            throw ValidationException::withMessages([
                'sales_amount_end' => 'A sales target end must be greater than or equal to its start.',
            ]);
        }
        if ((float) $commission_percent < 0 || (float) $commission_percent > 100) {
            throw ValidationException::withMessages([
                'commission' => 'Commission must be between 0 and 100 percent.',
            ]);
        }

        return [
            'id' => $target_id,
            'target_start' => round((float) $target_start, 4),
            'target_end' => round((float) $target_end, 4),
            'commission_percent' => round((float) $commission_percent, 4),
        ];
    }
}
