<?php

namespace Modules\Hms\Http\Controllers;

use App\Contact;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsGroupBooking;
use Modules\Hms\Entities\HmsGroupRoomBlock;
use Modules\Hms\Entities\HmsProperty;
use Modules\Hms\Entities\HmsRoom;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\FolioService;

class GroupBookingController extends Controller
{
    use AuthorizesHmsRequests;

    public function index()
    {
        $businessId = $this->authorizeHms('hms.manage_groups', 'hms_group_operations');
        $groups = HmsGroupBooking::where('business_id', $businessId)->with(['property', 'organizer', 'folio', 'roomBlocks.room', 'roomBlocks.booking'])->latest()->get();
        $properties = HmsProperty::where('business_id', $businessId)->where('is_active', true)->pluck('name', 'id');
        $contacts = Contact::where('business_id', $businessId)->whereIn('type', ['customer', 'both'])->pluck('name', 'id');
        $rooms = HmsRoom::whereHas('type', fn ($query) => $query->where('business_id', $businessId))->with('type')->orderBy('room_number')->get();

        return view('hms::groups.index', compact('groups', 'properties', 'contacts', 'rooms'));
    }

    public function store(Request $request, FolioService $folios)
    {
        $businessId = $this->authorizeHms('hms.manage_groups', 'hms_group_operations');
        $data = $request->validate([
            'hms_property_id' => ['required', 'integer', Rule::exists('hms_properties', 'id')->where('business_id', $businessId)],
            'organizer_contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('business_id', $businessId)],
            'name' => 'required|string|max:191',
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('hms_group_bookings', 'code')->where('business_id', $businessId)],
            'status' => ['required', Rule::in(['tentative', 'confirmed'])],
            'arrival_at' => 'required|date',
            'departure_at' => 'required|date|after:arrival_at',
            'release_at' => 'nullable|date|before_or_equal:arrival_at',
            'billing_instruction' => ['required', Rule::in(['individual', 'master', 'split'])],
            'deposit_required' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:5000',
        ]);
        DB::transaction(function () use ($data, $businessId, $folios) {
            $group = HmsGroupBooking::create($data + ['business_id' => $businessId, 'created_by' => auth()->id()]);
            if (in_array($group->billing_instruction, ['master', 'split'], true)) {
                $folio = $folios->ensureForGroup($group, (int) auth()->id());
                if ((float) $group->deposit_required > 0) {
                    $dueDate = optional($group->release_at ?: $group->arrival_at)->toDateString() ?: now()->toDateString();
                    $folios->scheduleDeposit(
                        $folio,
                        (float) $group->deposit_required,
                        $dueDate,
                        (int) auth()->id(),
                        __('hms::lang.group_deposit_requirement')
                    );
                }
            }
        });

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.group_saved')]);
    }

    public function addBlock(Request $request, $group)
    {
        $businessId = $this->authorizeHms('hms.manage_groups', 'hms_group_operations');
        $model = HmsGroupBooking::where('business_id', $businessId)->findOrFail($group);
        $data = $request->validate([
            'hms_room_id' => ['required', 'integer'],
            'agreed_rate' => 'nullable|numeric|min:0',
            'release_at' => 'nullable|date',
        ]);
        if (! empty($data['release_at'])
            && Carbon::parse($data['release_at'])->greaterThan($model->arrival_at)) {
            throw ValidationException::withMessages([
                'release_at' => __('hms::lang.release_before_arrival'),
            ]);
        }
        $room = HmsRoom::where('hms_property_id', $model->hms_property_id)
            ->whereHas('type', fn ($query) => $query->where('business_id', $businessId))
            ->findOrFail($data['hms_room_id']);
        if ($room->housekeeping_status === 'out_of_order') {
            throw ValidationException::withMessages(['hms_room_id' => __('hms::lang.room_out_of_order')]);
        }

        $bookingConflict = DB::table('hms_booking_lines as line')
            ->join('transactions as booking', 'booking.id', '=', 'line.transaction_id')
            ->where('line.hms_room_id', $room->id)
            ->where('booking.business_id', $businessId)
            ->where('booking.type', 'hms_booking')
            ->whereIn('booking.hms_booking_status', ['tentative', 'reserved', 'checked_in'])
            ->where('booking.hms_booking_arrival_date_time', '<', $model->departure_at)
            ->where('booking.hms_booking_departure_date_time', '>', $model->arrival_at)
            ->exists();
        $blockConflict = HmsGroupRoomBlock::where('business_id', $businessId)
            ->where('hms_room_id', $room->id)->whereIn('status', ['held', 'picked_up'])
            ->where('hms_group_booking_id', '!=', $model->id)
            ->whereHas('group', fn ($query) => $query
                ->where('arrival_at', '<', $model->departure_at)
                ->where('departure_at', '>', $model->arrival_at))
            ->exists();
        if ($bookingConflict || $blockConflict) {
            throw ValidationException::withMessages(['hms_room_id' => __('hms::lang.room_block_conflict')]);
        }

        HmsGroupRoomBlock::updateOrCreate([
            'hms_group_booking_id' => $model->id,
            'hms_room_id' => $room->id,
        ], [
            'business_id' => $businessId,
            'agreed_rate' => $data['agreed_rate'] ?? null,
            'release_at' => $data['release_at'] ?? $model->release_at,
            'status' => 'held',
            'created_by' => auth()->id(),
        ]);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.room_block_saved')]);
    }

    public function releaseBlock($block)
    {
        $businessId = $this->authorizeHms('hms.manage_groups', 'hms_group_operations');
        $model = HmsGroupRoomBlock::where('business_id', $businessId)->findOrFail($block);
        if ($model->status === 'picked_up' || $model->transaction_id) {
            throw ValidationException::withMessages([
                'block' => __('hms::lang.room_block_picked_up_cannot_release'),
            ]);
        }
        $model->update(['status' => 'released']);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.room_block_released')]);
    }
}
