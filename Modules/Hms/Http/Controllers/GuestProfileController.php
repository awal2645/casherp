<?php

namespace Modules\Hms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Hms\Entities\HmsGuestProfile;
use Modules\Hms\Entities\HmsTransactionClass;
use Modules\Hms\Http\Controllers\Concerns\AuthorizesHmsRequests;
use Modules\Hms\Services\GuestProfileService;

class GuestProfileController extends Controller
{
    use AuthorizesHmsRequests;

    public function index(Request $request)
    {
        $businessId = $this->authorizeHms('hms.manage_guests', 'hms_guest_experience');
        $query = HmsGuestProfile::where('business_id', $businessId)->with('contact');
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->whereHas('contact', fn ($contact) => $contact->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('mobile', 'like', $term));
        }
        $profiles = $query->latest()->paginate(30);

        return view('hms::guests.index', compact('profiles'));
    }

    public function update(Request $request, $profile, GuestProfileService $service)
    {
        $businessId = $this->authorizeHms('hms.manage_guests', 'hms_guest_experience');
        $model = HmsGuestProfile::where('business_id', $businessId)->findOrFail($profile);
        $data = $request->validate([
            'preferred_language' => 'nullable|string|max:12',
            'nationality' => 'nullable|string|max:80',
            'date_of_birth' => 'nullable|date|before:today',
            'identity_document_type' => 'nullable|string|max:40',
            'identity_document_last_four' => 'nullable|string|max:8',
            'vip_level' => ['nullable', Rule::in(['standard', 'silver', 'gold', 'platinum'])],
            'preferences' => 'nullable|array',
            'accessibility_needs' => 'nullable|string|max:3000',
            'retention_until' => 'nullable|date',
            'marketing_consent' => 'nullable|boolean',
            'do_not_contact' => 'nullable|boolean',
        ]);
        $data['marketing_consent'] = $request->boolean('marketing_consent');
        $data['do_not_contact'] = $request->boolean('do_not_contact');
        $data['consent_source'] = 'profile_management';
        $service->sync($businessId, $model->contact_id, $data);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.guest_profile_saved')]);
    }

    public function export($profile)
    {
        $businessId = $this->authorizeHms('hms.manage_guests', 'hms_guest_experience');
        $model = HmsGuestProfile::where('business_id', $businessId)->with('contact')->findOrFail($profile);
        $stays = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')
            ->where('contact_id', $model->contact_id)
            ->get(['ref_no', 'hms_booking_arrival_date_time', 'hms_booking_departure_date_time', 'hms_booking_status', 'final_total']);

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'guest' => [
                'name' => optional($model->contact)->name,
                'email' => optional($model->contact)->email,
                'mobile' => optional($model->contact)->mobile,
                'profile' => $model->makeHidden(['business_id', 'contact_id', 'created_at', 'updated_at'])->toArray(),
            ],
            'stays' => $stays,
        ])->header('Content-Disposition', 'attachment; filename="guest-data-' . $model->id . '.json"');
    }

    public function anonymize($profile, GuestProfileService $service)
    {
        $businessId = $this->authorizeHms('hms.anonymize_guests', 'hms_guest_experience');
        $model = HmsGuestProfile::where('business_id', $businessId)->findOrFail($profile);
        $activeStay = HmsTransactionClass::where('business_id', $businessId)->where('type', 'hms_booking')
            ->where('contact_id', $model->contact_id)->whereIn('hms_booking_status', ['tentative', 'reserved', 'checked_in'])->exists();
        if ($activeStay || ($model->retention_until && $model->retention_until->isFuture())) {
            throw ValidationException::withMessages(['profile' => __('hms::lang.guest_anonymization_blocked')]);
        }
        $service->anonymize($model);

        return back()->with('status', ['success' => 1, 'msg' => __('hms::lang.guest_profile_anonymized')]);
    }
}
