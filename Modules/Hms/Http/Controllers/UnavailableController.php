<?php

namespace Modules\Hms\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Entities\HmsRoomUnavailable;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnavailableController extends Controller
{
    protected $commonUtil;
    protected $notificationUtil;
    protected $moduleUtil;

    public function __construct(
        Util $commonUtil, ModuleUtil $moduleUtil

    ) {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {

        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        if (request()->ajax()) {
            $unavailables = HmsRoomUnavailable::
                    leftjoin('hms_rooms as room', 'room.id', '=', 'hms_room_unavailables.hms_rooms_id')
                    ->leftjoin('hms_room_types as type', 'type.id', '=', 'room.hms_room_type_id')
                    ->where('type.business_id', $business_id)
                    ->select(['hms_room_unavailables.id as id', 'hms_room_unavailables.date_from','hms_room_unavailables.date_to','hms_room_unavailables.unavailable_type','hms_room_unavailables.hms_rooms_id','hms_room_unavailables.created_at as created_at','type.type as type', 'room.room_number as room_number']);
            return Datatables::of($unavailables)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')

                ->editColumn('room_number', function($row){
                    return $row->type .' - '.$row->room_number;
                })
                ->editColumn('unavailable_type', function($row){
                    return str_replace('_',' ', $row->unavailable_type);
                })
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal-unavailable" href="' . action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'edit'], ['unavailable' => $row->id]) . '">'
                        . __('hms::lang.edit_unavailable') . '</a>';
                    $html .= '<form method="POST" action="'.route('hms.delete_unavailable', ['id' => $row->id]).'" class="tw-inline-block hms-delete-form">'
                        .csrf_field().method_field('DELETE')
                        .'<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_unavailable_confirmation">'
                        .__('messages.delete').'</button></form>';

                    return $html;
                })
                ->editColumn('date_from', '{{@format_date($date_from)}}')
                ->editColumn('date_to', '{{@format_date($date_to)}}')

                ->rawColumns(['created_at', 'action', 'room_number', 'date_from', 'date_to'])
                ->make(true);
        }

        return view('hms::unavailables.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
                            ->where('type.business_id', $business_id)
                            ->selectRaw("CONCAT( type.type, ' - ', hms_rooms.room_number) AS room_description, hms_rooms.id")
                            ->pluck('room_description', 'hms_rooms.id')->toArray();
    
        $types = [
            'unavailable' => 'Unavailable',
            // 'stop_from_web' => 'Stop from web',
            // 'external_booking' => 'External Booking',
        ];

        return view('hms::unavailables.create', compact('rooms', 'types'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $validated = $request->validate([
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'required|integer',
            'date_from' => 'required|string|max:50',
            'date_to' => 'required|string|max:50',
            'unavailable_type' => ['required', Rule::in(['unavailable'])],
        ]);

        try{
            $dateFrom = $this->commonUtil->uf_date($validated['date_from']);
            $dateTo = $this->commonUtil->uf_date($validated['date_to']);
            $this->assertDateRange($dateFrom, $dateTo);
            $roomIds = array_values(array_unique(array_map('intval', $validated['rooms'])));
            $this->assertRoomsBelongToBusiness($roomIds, (int) $business_id);

            DB::transaction(function () use ($roomIds, $dateFrom, $dateTo, $validated) {
                foreach ($roomIds as $roomId) {
                    $this->assertNoOverlap($roomId, $dateFrom, $dateTo);
                    HmsRoomUnavailable::create([
                        'hms_rooms_id' => $roomId,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'unavailable_type' => $validated['unavailable_type'],
                    ]);
                }
            });

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
                ->with('status', $output);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('hms::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }
        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $unavailable = $this->scopedUnavailable((int) $id, (int) $business_id);


        $rooms = HmsRoom::leftjoin('hms_room_types as type', 'type.id', '=', 'hms_rooms.hms_room_type_id')
                            ->where('type.business_id', $business_id)
                            ->selectRaw("CONCAT( type.type, ' - ', hms_rooms.room_number) AS room_description, hms_rooms.id")
                            ->pluck('room_description', 'hms_rooms.id')->toArray();
    
        $types = [
            'unavailable' => 'Unavailable',
            // 'stop_from_web' => 'Stop from web',
            // 'external_booking' => 'External Booking',
        ];

        return view('hms::unavailables.edit', compact('rooms', 'types', 'unavailable'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        $unavailable = $this->scopedUnavailable((int) $id, (int) $business_id);

        $validated = $request->validate([
            'room_id' => 'required|integer',
            'date_from' => 'required|string|max:50',
            'date_to' => 'required|string|max:50',
            'unavailable_type' => ['required', Rule::in(['unavailable'])],
        ]);
        try{
            $roomId = (int) $validated['room_id'];
            $dateFrom = $this->commonUtil->uf_date($validated['date_from']);
            $dateTo = $this->commonUtil->uf_date($validated['date_to']);
            $this->assertDateRange($dateFrom, $dateTo);
            $this->assertRoomsBelongToBusiness([$roomId], (int) $business_id);
            $this->assertNoOverlap($roomId, $dateFrom, $dateTo, (int) $unavailable->id);

            $unavailable->hms_rooms_id = $roomId;
            $unavailable->date_from = $dateFrom;
            $unavailable->date_to = $dateTo;
            $unavailable->unavailable_type = $validated['unavailable_type'];
            $unavailable->update();
    
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
                ->with('status', $output);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'hms_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'hms.manage_unavailable')){
            abort(403, 'Unauthorized action.');
        }


        try {

            $this->scopedUnavailable((int) $id, (int) $business_id)->delete();

            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }

    }

    private function scopedUnavailable(int $id, int $businessId): HmsRoomUnavailable
    {
        return HmsRoomUnavailable::whereKey($id)
            ->whereExists(function ($query) use ($businessId) {
                $query->select(DB::raw(1))
                    ->from('hms_rooms as scoped_room')
                    ->join('hms_room_types as scoped_type', 'scoped_type.id', '=', 'scoped_room.hms_room_type_id')
                    ->whereColumn('scoped_room.id', 'hms_room_unavailables.hms_rooms_id')
                    ->where('scoped_type.business_id', $businessId);
            })
            ->firstOrFail();
    }

    private function assertRoomsBelongToBusiness(array $roomIds, int $businessId): void
    {
        $count = HmsRoom::whereIn('hms_rooms.id', $roomIds)
            ->leftJoin('hms_room_types as scoped_type', 'scoped_type.id', '=', 'hms_rooms.hms_room_type_id')
            ->where('scoped_type.business_id', $businessId)
            ->count('hms_rooms.id');

        if ($count !== count($roomIds)) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.invalid_room_selection'),
            ]);
        }
    }

    private function assertNoOverlap(
        int $roomId,
        string $dateFrom,
        string $dateTo,
        ?int $excludeId = null
    ): void {
        $overlap = HmsRoomUnavailable::where('hms_rooms_id', $roomId)
            ->whereDate('date_from', '<=', $dateTo)
            ->whereDate('date_to', '>=', $dateFrom)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'rooms' => __('hms::lang.unavailable_period_overlap'),
            ]);
        }
    }

    private function assertDateRange(string $dateFrom, string $dateTo): void
    {
        if ($dateTo < $dateFrom) {
            throw ValidationException::withMessages([
                'date_to' => __('hms::lang.end_date_after_start'),
            ]);
        }
    }
}
