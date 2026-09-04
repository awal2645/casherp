<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Http\Requests\StorePurchaseRequisitionRequest;
use App\PurchaseLine;
use App\ProcurementDocument;
use App\Services\ProcurementWorkflowService;
use App\TaxRate;
use App\Transaction;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\VariationLocationDetails;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PurchaseRequisitionController extends Controller
{
    protected $commonUtil;

    protected $transactionUtil;

    protected $procurementWorkflow;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(Util $commonUtil, TransactionUtil $transactionUtil, ProcurementWorkflowService $procurementWorkflow)
    {
        $this->commonUtil = $commonUtil;
        $this->transactionUtil = $transactionUtil;
        $this->procurementWorkflow = $procurementWorkflow;

        $this->purchaseRequisitionStatuses = [
            'ordered' => [
                'label' => __('lang_v1.ordered'),
                'class' => 'bg-info',
            ],
            'partial' => [
                'label' => __('lang_v1.partial'),
                'class' => 'bg-yellow',
            ],
            'completed' => [
                'label' => __('restaurant.completed'),
                'class' => 'bg-green',
            ],
            'draft' => [
                'label' => 'Draft / awaiting approval',
                'class' => 'bg-gray',
            ],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $hasWorkflowAccess = auth()->user()->hasAnyPermission(['procurement.audit.view', 'procurement.quote.manage', 'procurement.approve.manager', 'procurement.approve.finance', 'procurement.approve.admin']);
        $hasSubmitAccess = auth()->user()->can('purchase_requisition.create') && auth()->user()->can('procurement.submit');
        if (! auth()->user()->can('purchase_requisition.view_all') && ! auth()->user()->can('purchase_requisition.view_own') && ! $hasWorkflowAccess && ! $hasSubmitAccess) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $purchase_requisitions = Transaction::join(
                        'business_locations AS BS',
                        'transactions.location_id',
                        '=',
                        'BS.id'
                    )
                    ->leftJoin('procurement_documents as pd', 'pd.transaction_id', '=', 'transactions.id')
                    ->join('users as u', 'transactions.created_by', '=', 'u.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase_requisition')
                    ->select(
                        'transactions.id',
                        'transactions.created_by',
                        'transactions.delivery_date',
                        'transactions.ref_no',
                        'transactions.status',
                        'pd.approval_status',
                        'BS.name as location_name',
                        'transactions.transaction_date',
                        DB::raw("CONCAT(COALESCE(u.surname, ''),' ',COALESCE(u.first_name, ''),' ',COALESCE(u.last_name,'')) as added_by")
                    )
                    ->groupBy('transactions.id');

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $purchase_requisitions->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->location_id)) {
                $purchase_requisitions->where('transactions.location_id', request()->location_id);
            }

            if (! empty(request()->status)) {
                $purchase_requisitions->where('transactions.status', request()->status);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $purchase_requisitions->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            if (! empty(request()->required_by_start) && ! empty(request()->required_by_end)) {
                $start = request()->required_by_start;
                $end = request()->required_by_end;
                $purchase_requisitions->whereDate('transactions.delivery_date', '>=', $start)
                            ->whereDate('transactions.delivery_date', '<=', $end);
            }

            if (! auth()->user()->can('purchase_requisition.view_all') && ! $hasWorkflowAccess) {
                $purchase_requisitions->where('transactions.created_by', request()->session()->get('user.id'));
            }

            if (! empty(request()->from_dashboard)) {
                $purchase_requisitions->where('transactions.status', '!=', 'completed');
            }

            return Datatables::of($purchase_requisitions)
                ->addColumn('action', function ($row) use ($business_id) {
                    $html = '<div class="btn-group">
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max  dropdown-toggle" 
                                data-toggle="dropdown" aria-expanded="false">'.
                                __('messages.actions').
                                '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                    $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\PurchaseRequisitionController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    if (! empty($row->approval_status)) {
                        $html .= '<li><a href="'.route('procurement.workflow.show', $row->id).'"><i class="fas fa-file-signature" aria-hidden="true"></i>Approval workspace</a></li>';
                    }

                    $canCorrect = $row->approval_status === 'rejected'
                        && auth()->user()->can('purchase_requisition.create')
                        && auth()->user()->can('procurement.submit')
                        && ((int) $row->created_by === (int) auth()->id()
                            || auth()->user()->hasRole('Admin#'.$business_id)
                            || auth()->user()->can('superadmin'));
                    if ($canCorrect) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseRequisitionController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit" aria-hidden="true"></i>Correct requisition</a></li>';
                    }

                    if (auth()->user()->can('purchase_requisition.delete') && in_array($row->approval_status, [null, 'rejected'], true)) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PurchaseRequisitionController::class, 'destroy'], [$row->id]).'" class="delete-purchase-requisition"><i class="fas fa-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->editColumn('delivery_date', '@if(!empty($delivery_date)){{@format_datetime($delivery_date)}}@endif')
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn('status', function ($row) {
                    $order_statuses = $this->purchaseRequisitionStatuses;
                    if (! empty($row->approval_status)) {
                        $class = $row->approval_status === 'approved' ? 'bg-green' : ($row->approval_status === 'rejected' ? 'bg-red' : 'bg-yellow');
                        $approval = '<span class="label '.$class.'">'.e(ucwords(str_replace('_', ' ', $row->approval_status))).'</span>';
                        if ($row->approval_status === 'approved' && array_key_exists($row->status, $order_statuses)) {
                            $approval .= ' <span class="label '.$order_statuses[$row->status]['class'].'">'.$order_statuses[$row->status]['label'].'</span>';
                        }

                        return $approval;
                    }
                    $status = '';
                    if (array_key_exists($row->status, $order_statuses)) {
                        $status = '<span class="label '.$order_statuses[$row->status]['class']
                            .'" >'.$order_statuses[$row->status]['label'].'</span>';
                    }

                    return $status;
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        return  action([\App\Http\Controllers\PurchaseRequisitionController::class, 'show'], [$row->id]);
                    }, ])
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);

        $purchaseRequisitionStatuses = [];
        foreach ($this->purchaseRequisitionStatuses as $key => $value) {
            $purchaseRequisitionStatuses[$key] = $value['label'];
        }

        return view('purchase_requisition.index')->with(compact('business_locations', 'purchaseRequisitionStatuses'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('purchase_requisition.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id);

        $categories = Category::forDropdown($business_id, 'product');

        $brands = Brands::forDropdown($business_id);

        $departments = Schema::hasTable('categories')
            ? Category::forDropdown($business_id, 'hrm_department')
            : collect();
        $projects = Schema::hasTable('pjt_projects')
            ? DB::table('pjt_projects')->where('business_id', $business_id)->whereNotIn('status', ['cancelled', 'completed'])->orderBy('name')->pluck('name', 'id')
            : collect();
        $defaultDepartmentId = auth()->user()->essentials_department_id;

        return view('purchase_requisition.create')->with(compact('business_locations', 'categories', 'brands', 'departments', 'projects', 'defaultDepartmentId'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StorePurchaseRequisitionRequest $request)
    {
        if (! auth()->user()->can('purchase_requisition.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $business = Business::findOrFail($business_id);

            $transaction_data = [
                'business_id' => $business_id,
                'location_id' => $request->input('location_id'),
                'type' => 'purchase_requisition',
                'status' => 'draft',
                'created_by' => auth()->user()->id,
                'transaction_date' => \Carbon::now()->toDateTimeString(),
                'ref_no' => $request->input('ref_no'),
                'additional_notes' => $request->input('purpose'),
            ];

            $transaction_data['delivery_date'] = ! empty($request->input('delivery_date')) ? $this->commonUtil->uf_date($request->input('delivery_date'), true) : null;

            $purchase_lines = $this->purchaseLinesFromRequest($request);

            DB::beginTransaction();

            //Update reference count
            $ref_count = $this->commonUtil->setAndGetReferenceCount($transaction_data['type']);
            //Generate reference number
            if (empty($transaction_data['ref_no'])) {
                $transaction_data['ref_no'] = $this->commonUtil->generateReferenceNumber($transaction_data['type'], $ref_count);
            }

            $purchase_requisition = Transaction::create($transaction_data);
            $purchase_requisition->purchase_lines()->createMany($purchase_lines);
            $this->procurementWorkflow->begin($purchase_requisition, 'requisition', [
                'department_id' => $request->input('department_id') ?: auth()->user()->essentials_department_id,
                'project_id' => $request->input('project_id'),
                'currency_id' => $business->currency_id,
                'requested_by' => auth()->id(),
                'priority' => $request->input('priority'),
                'purpose' => $request->input('purpose'),
                'budget_amount' => $request->filled('budget_amount')
                    ? $this->commonUtil->num_uf($request->input('budget_amount'))
                    : null,
            ]);

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->action([\App\Http\Controllers\PurchaseRequisitionController::class, 'index'])->with('status', $output);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $purchase = $this->authorizedRequisition((int) $id);
        $business_id = (int) request()->session()->get('user.business_id');
        $canShareDocument = auth()->user()->can('superadmin')
            || auth()->user()->hasRole('Admin#'.$business_id)
            || auth()->user()->canForBusiness('purchase.document.share', $business_id);

        return view('purchase_requisition.show')
                ->with(compact('purchase', 'canShareDocument'));
    }

    public function printDocument($id)
    {
        return $this->requisitionPdfResponse((int) $id, 'inline', 'print');
    }

    public function downloadDocument($id)
    {
        return $this->requisitionPdfResponse((int) $id, 'attachment', 'download');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('purchase_requisition.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = (int) request()->session()->get('user.business_id');
        $purchase = Transaction::where('business_id', $business_id)
            ->where('type', 'purchase_requisition')
            ->with([
                'purchase_lines.product.unit', 'purchase_lines.product.second_unit',
                'purchase_lines.variations.product_variation', 'procurementDocument',
            ])->findOrFail($id);
        $this->assertCanCorrect($purchase, $business_id);

        $business_locations = BusinessLocation::forDropdown($business_id);
        $categories = Category::forDropdown($business_id, 'product');
        $brands = Brands::forDropdown($business_id);
        $departments = Category::forDropdown($business_id, 'hrm_department');
        $projects = Schema::hasTable('pjt_projects')
            ? DB::table('pjt_projects')->where('business_id', $business_id)->whereNotIn('status', ['cancelled', 'completed'])->orderBy('name')->pluck('name', 'id')
            : collect();
        $defaultDepartmentId = $purchase->procurementDocument->department_id;
        $deliveryDate = $purchase->delivery_date
            ? $this->commonUtil->format_date($purchase->delivery_date, true)
            : null;

        return view('purchase_requisition.create')->with(compact(
            'purchase', 'business_locations', 'categories', 'brands',
            'departments', 'projects', 'defaultDepartmentId', 'deliveryDate'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(StorePurchaseRequisitionRequest $request, $id)
    {
        $business_id = (int) $request->session()->get('user.business_id');
        $purchaseLines = $this->purchaseLinesFromRequest($request);

        DB::transaction(function () use ($request, $id, $business_id, $purchaseLines) {
            $purchase = Transaction::where('business_id', $business_id)
                ->where('type', 'purchase_requisition')
                ->with(['purchase_lines', 'procurementDocument'])
                ->lockForUpdate()
                ->findOrFail($id);
            $this->assertCanCorrect($purchase, $business_id);

            $document = ProcurementDocument::where('business_id', $business_id)
                ->where('transaction_id', $purchase->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (PurchaseLine::whereIn('purchase_requisition_line_id', $purchase->purchase_lines->pluck('id'))->exists()) {
                throw ValidationException::withMessages([
                    'workflow' => 'A requisition linked to a purchase order can no longer be corrected.',
                ]);
            }

            $document->quotes()->delete();
            $purchase->purchase_lines()->delete();
            $purchase->update([
                'location_id' => $request->integer('location_id'),
                'ref_no' => $request->input('ref_no') ?: $purchase->ref_no,
                'delivery_date' => $this->commonUtil->uf_date($request->input('delivery_date'), true),
                'additional_notes' => $request->input('purpose'),
                'status' => 'draft',
            ]);
            $purchase->purchase_lines()->createMany($purchaseLines);
            $document->update([
                'department_id' => $request->input('department_id') ?: auth()->user()->essentials_department_id,
                'project_id' => $request->input('project_id'),
                'priority' => $request->input('priority'),
                'purpose' => $request->input('purpose'),
                'budget_amount' => $request->filled('budget_amount')
                    ? $this->commonUtil->num_uf($request->input('budget_amount'))
                    : null,
                'selected_quote_id' => null,
                'lock_version' => DB::raw('lock_version + 1'),
            ]);
            $this->procurementWorkflow->correctionsSaved($document, $request->user(), [
                'quotation_reset' => true,
                'line_count' => count($purchaseLines),
            ]);
            $this->transactionUtil->activityLog($purchase, 'edited');
        });

        return redirect()->route('procurement.workflow.show', $id)->with('status', [
            'success' => 1,
            'msg' => 'Corrections saved. Review the requisition and resubmit it for approval.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('purchase_requisition.delete')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            if (request()->ajax()) {
                $business_id = request()->session()->get('user.business_id');

                $transaction = Transaction::where('business_id', $business_id)
                                ->where('type', 'purchase_requisition')
                                ->with(['purchase_lines', 'procurementDocument'])
                                ->findOrFail($id);

                if ($transaction->procurementDocument
                    && $transaction->procurementDocument->approval_status !== 'rejected') {
                    throw new \DomainException('Only a rejected requisition without an active approval can be deleted.');
                }

                if (PurchaseLine::whereIn('purchase_requisition_line_id', $transaction->purchase_lines->pluck('id'))->exists()) {
                    throw new \DomainException('This requisition is linked to a purchase order and cannot be deleted.');
                }

                //unset purchase_order_line_id if set
                PurchaseLine::whereIn('purchase_requisition_line_id', $transaction->purchase_lines->pluck('id'))
                        ->update(['purchase_requisition_line_id' => null]);

                $transaction->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => $e->getMessage(),
            ];
        }

        return $output;
    }

    public function getRequisitionProducts()
    {
        if (! auth()->user()->can('purchase_requisition.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $query = VariationLocationDetails::join(
                'product_variations as pv',
                'variation_location_details.product_variation_id',
                '=',
                'pv.id'
            )
                    ->join(
                        'variations as v',
                        'variation_location_details.variation_id',
                        '=',
                        'v.id'
                    )
                    ->join(
                        'products as p',
                        'variation_location_details.product_id',
                        '=',
                        'p.id'
                    )
                    ->leftjoin(
                        'business_locations as l',
                        'variation_location_details.location_id',
                        '=',
                        'l.id'
                    )
                    ->leftjoin('units as u', 'p.unit_id', '=', 'u.id')
                    ->leftjoin('units as su', 'p.secondary_unit_id', '=', 'su.id')
                    ->where('p.business_id', $business_id)
                    ->where('p.enable_stock', 1)
                    ->where('p.is_inactive', 0)
                    ->whereNull('v.deleted_at')
                    ->whereNotNull('p.alert_quantity')
                    ->whereRaw('variation_location_details.qty_available <= p.alert_quantity');

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('variation_location_details.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('variation_location_details.location_id', request()->input('location_id'));
            }
            if (! empty(request()->input('brand_id'))) {
                $query->whereIn('p.brand_id', request()->input('brand_id'));
            }

            if (! empty(request()->input('category_id'))) {
                $query->whereIn('p.category_id', request()->input('category_id'));
            }

            $products = $query->select(
                'p.name as product',
                'p.type',
                'p.sku',
                'p.alert_quantity',
                'pv.name as product_variation',
                'v.name as variation',
                'v.sub_sku',
                'l.name as location',
                'variation_location_details.qty_available as stock',
                'u.short_name as unit',
                'v.id as variation_id',
                'p.id as product_id',
                'u.allow_decimal',
                'su.short_name as second_unit',
                'su.allow_decimal as su_allow_decimal'

            )
            ->groupBy('v.id')
            ->get();

            return view('purchase_requisition.product_list')->with(compact('products'));
        }
    }

    public function getPurchaseRequisitions($location_id)
    {
        if (! auth()->user()->can('purchase_order.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        BusinessLocation::where('business_id', $business_id)->findOrFail($location_id);

        $purchase_requisitions = Transaction::where('business_id', $business_id)
                        ->where('type', 'purchase_requisition')
                        ->whereIn('status', ['partial', 'ordered'])
                        ->where('location_id', $location_id)
                        ->select('ref_no as text', 'id')
                        ->get();

        return $purchase_requisitions;
    }

    public function getPurchaseRequisitionLines($purchase_requisition_id)
    {
        if (! auth()->user()->can('purchase_order.create') || ! auth()->user()->can('procurement.submit')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $purchase_requisition = Transaction::where('business_id', $business_id)
                        ->where('type', 'purchase_requisition')
                        ->whereIn('status', ['partial', 'ordered'])
                        ->with(['purchase_lines', 'purchase_lines.variations',
                            'purchase_lines.product', 'purchase_lines.product.unit', 'purchase_lines.variations.product_variation', ])
                        ->findOrFail($purchase_requisition_id);

        $taxes = TaxRate::where('business_id', $business_id)
                            ->ExcludeForTaxGroup()
                            ->get();

        $sub_units_array = [];
        foreach ($purchase_requisition->purchase_lines as $pl) {
            $sub_units_array[$pl->id] = $this->transactionUtil->getSubUnits($business_id, $pl->product->unit->id, false, $pl->product_id);
        }
        $hide_tax = request()->session()->get('business.enable_inline_tax') == 1 ? '' : 'hide';
        $currency_details = $this->transactionUtil->purchaseCurrencyDetails($business_id);
        $row_count = request()->input('row_count');
        $is_purchase_order = true;
        $html = view('purchase_requisition.partials.purchase_requisition_lines')
                ->with(compact(
                    'purchase_requisition',
                    'taxes',
                    'hide_tax',
                    'currency_details',
                    'row_count',
                    'sub_units_array',
                    'is_purchase_order'
                ))->render();

        return [
            'html' => $html,
        ];
    }

    private function authorizedRequisition(int $id): Transaction
    {
        $businessId = (int) request()->session()->get('user.business_id');
        $user = auth()->user();
        $isAdmin = $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
        $hasWorkflowAccess = $user->hasAnyPermission([
            'procurement.audit.view',
            'procurement.quote.manage',
            'procurement.approve.manager',
            'procurement.approve.finance',
            'procurement.approve.admin',
        ]);
        $hasSubmitAccess = $user->canForBusiness('purchase_requisition.create', $businessId)
            && $user->canForBusiness('procurement.submit', $businessId);
        abort_unless(
            $isAdmin
                || $user->canForBusiness('purchase_requisition.view_all', $businessId)
                || $user->canForBusiness('purchase_requisition.view_own', $businessId)
                || $hasWorkflowAccess
                || $hasSubmitAccess,
            403,
            'Unauthorized action.'
        );

        $query = Transaction::where('business_id', $businessId)
            ->where('type', 'purchase_requisition')
            ->whereKey($id)
            ->with([
                'business',
                'purchase_lines.product.unit',
                'purchase_lines.product.second_unit',
                'purchase_lines.variations.product_variation',
                'location',
                'sales_person',
                'procurementDocument.department',
            ]);
        $permitted = $user->permitted_locations($businessId);
        if ($permitted !== 'all') {
            $query->whereIn('location_id', array_map('intval', (array) $permitted));
        }
        if (! $isAdmin
            && ! $user->canForBusiness('purchase_requisition.view_all', $businessId)
            && ! $hasWorkflowAccess) {
            $query->where('transactions.created_by', $user->id);
        }

        return $query->firstOrFail();
    }

    private function requisitionPdfResponse(int $id, string $disposition, string $action)
    {
        $purchase = $this->authorizedRequisition($id);
        $body = view('purchase_requisition.pdf', compact('purchase'))->render();
        $filename = Str::slug('PR-'.$purchase->ref_no).'.pdf';
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => public_path('uploads/temp'),
            'mode' => 'utf-8',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'format' => 'A4',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 12,
            'margin_left' => 10,
        ]);
        $mpdf->useSubstitutions = true;
        $mpdf->SetTitle($filename);
        $mpdf->WriteHTML($body);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-CashERP-Document-Action' => $action,
        ]);
    }

    private function purchaseLinesFromRequest(StorePurchaseRequisitionRequest $request): array
    {
        return collect($request->input('purchases', []))->map(function ($purchaseLine) {
            return [
                'variation_id' => $purchaseLine['variation_id'],
                'product_id' => $purchaseLine['product_id'],
                'quantity' => $this->commonUtil->num_uf($purchaseLine['quantity'] ?? 0),
                'purchase_price_inc_tax' => 0,
                'item_tax' => 0,
                'secondary_unit_quantity' => $this->commonUtil->num_uf($purchaseLine['secondary_unit_quantity'] ?? 0),
            ];
        })->values()->all();
    }

    private function assertCanCorrect(Transaction $purchase, int $businessId): void
    {
        $isAdmin = auth()->user()->hasRole('Admin#'.$businessId) || auth()->user()->can('superadmin');
        if (! $purchase->procurementDocument || $purchase->procurementDocument->approval_status !== 'rejected') {
            abort(409, 'Only a rejected requisition can be corrected.');
        }
        if (! $isAdmin && (int) $purchase->created_by !== (int) auth()->id()) {
            abort(403, 'Only the requester or company administrator can correct this requisition.');
        }
    }
}
