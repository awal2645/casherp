<?php

namespace Modules\Essentials\Http\Controllers;

use App\Business;
use App\Utils\ModuleUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Utils\EssentialsUtil;

class AttendanceApiController extends Controller
{
    use AuthorizesHrmRequests;

    protected $moduleUtil;

    protected $essentialsUtil;

    public function __construct(ModuleUtil $moduleUtil, EssentialsUtil $essentialsUtil)
    {
        $this->moduleUtil = $moduleUtil;
        $this->essentialsUtil = $essentialsUtil;
    }

    public function status(Request $request): JsonResponse
    {
        $business_id = $this->authorizeApiAttendance($request);
        $attendance = EssentialsAttendance::where('business_id', $business_id)
            ->where('user_id', $request->user()->id)
            ->whereNull('clock_out_time')
            ->with('shift:id,name,type,start_time,end_time')
            ->first();

        return response()->json([
            'success' => true,
            'clocked_in' => ! empty($attendance),
            'attendance' => $attendance,
        ]);
    }

    public function clock(Request $request): JsonResponse
    {
        $business_id = $this->authorizeApiAttendance($request);
        $input = $request->validate([
            'type' => ['required', 'in:clock_in,clock_out'],
            'note' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:1000'],
        ]);
        $business = Business::findOrFail($business_id);
        $settings = ! empty($business->essentials_settings)
            ? json_decode($business->essentials_settings, true)
            : [];
        if (! empty($settings['is_location_required']) && empty($input['location'])) {
            return response()->json([
                'success' => false,
                'message' => __('essentials::lang.you_must_enable_location'),
            ], 422);
        }

        $timezone = in_array($business->time_zone, \DateTimeZone::listIdentifiers(), true)
            ? $business->time_zone
            : config('app.timezone');
        $now = \Carbon::now($timezone)->format('Y-m-d H:i:s');
        if ($input['type'] === 'clock_in') {
            $result = $this->essentialsUtil->clockin([
                'business_id' => $business_id,
                'user_id' => $request->user()->id,
                'clock_in_time' => $now,
                'clock_in_note' => $input['note'] ?? null,
                'ip_address' => $this->moduleUtil->getUserIpAddr(),
                'clock_in_location' => $input['location'] ?? null,
            ], $settings);
        } else {
            $result = $this->essentialsUtil->clockout([
                'business_id' => $business_id,
                'user_id' => $request->user()->id,
                'clock_out_time' => $now,
                'clock_out_note' => $input['note'] ?? null,
                'clock_out_location' => $input['location'] ?? null,
            ], $settings);
        }

        // Browser-only HTML is deliberately omitted from the API contract.
        unset($result['current_shift'], $result['shift_details']);
        $result['message'] = $result['msg'] ?? null;
        unset($result['msg']);

        return response()->json($result, ! empty($result['success']) ? 200 : 422);
    }

    private function authorizeApiAttendance(Request $request): int
    {
        $user = $request->user('api') ?: $request->user();
        abort_if(empty($user), 403, 'Unauthorized action.');

        $business_id = (int) ($request->header('X-Business-Id') ?: $request->input('business_id', $user->business_id));
        abort_unless($business_id > 0 && $user->canAccessBusiness($business_id), 403, 'Unauthorized company context.');
        $this->authorizeHrmFeature($business_id);

        $is_admin = $this->moduleUtil->is_admin($user, $business_id);
        abort_unless(
            $user->can('superadmin') || $is_admin || $user->canForBusiness('essentials.allow_users_for_attendance_from_api', $business_id),
            403,
            'Unauthorized action.'
        );

        return $business_id;
    }
}
