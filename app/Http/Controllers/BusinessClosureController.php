<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessClosureRequest;
use App\Notifications\BusinessClosureNotification;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BusinessClosureController extends Controller
{
    public function show(Request $request)
    {
        $business = $this->authorisedBusiness($request);

        return view('business.account_closure', [
            'business' => $business,
            'closure' => BusinessClosureRequest::where('business_id', $business->id)
                ->where('status', 'scheduled')
                ->latest()
                ->first(),
            'recoveryDays' => 30,
        ]);
    }

    public function schedule(Request $request)
    {
        $business = $this->authorisedBusiness($request);
        $data = $request->validate([
            'confirmation_name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'acknowledge' => ['accepted'],
        ]);

        if (! hash_equals($business->name, $data['confirmation_name'])) {
            throw ValidationException::withMessages([
                'confirmation_name' => 'Enter the company name exactly as shown.',
            ]);
        }
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $closure = DB::transaction(function () use ($business, $request, $data) {
            $exists = BusinessClosureRequest::where('business_id', $business->id)
                ->where('status', 'scheduled')
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'closure' => 'Company closure is already scheduled.',
                ]);
            }

            return BusinessClosureRequest::create([
                'business_id' => $business->id,
                'requested_by' => $request->user()->id,
                'status' => 'scheduled',
                'reason' => $data['reason'] ?? null,
                'scheduled_for' => now()->addDays(30),
                'request_ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'company_snapshot' => [
                    'name' => $business->name,
                    'industry' => optional($business->industry)->name,
                    'owner_id' => $business->owner_id,
                    'locations' => $business->locations()->count(),
                    'requested_at' => now()->toIso8601String(),
                ],
            ]);
        });
        $this->notifyClosureStakeholders($closure, 'scheduled');

        return redirect()->route('business.account-closure.show')->with('status', [
            'success' => 1,
            'msg' => 'Company closure is scheduled. You may cancel it during the 30-day recovery window.',
        ]);
    }

    public function cancel(Request $request)
    {
        $business = $this->authorisedBusiness($request);
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        $closure = BusinessClosureRequest::where('business_id', $business->id)
            ->where('status', 'scheduled')
            ->latest()
            ->firstOrFail();
        $closure->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);
        $this->notifyClosureStakeholders($closure->refresh(), 'cancelled');

        return redirect()->route('business.account-closure.show')->with('status', [
            'success' => 1,
            'msg' => 'Company closure was cancelled.',
        ]);
    }

    private function authorisedBusiness(Request $request): Business
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $business = Business::with('industry')->findOrFail($businessId);
        $isOwner = (int) $business->owner_id === (int) $request->user()->id;
        abort_unless(
            $isOwner || $request->user()->can('business.account_closure.manage'),
            403,
            'Only the company owner or an expressly authorised administrator may close this company.'
        );

        return $business;
    }

    private function notifyClosureStakeholders(BusinessClosureRequest $closure, string $event): void
    {
        try {
            $closure->loadMissing('business');
            $userIds = collect([
                $closure->requested_by,
                optional($closure->business)->owner_id,
            ])->filter()->unique()->values();

            User::whereIn('id', $userIds)->get()->each(function (User $user) use ($closure, $event) {
                $user->notify(new BusinessClosureNotification($closure, $event));
            });
        } catch (\Throwable $exception) {
            Log::warning('Unable to create company closure notification.', [
                'closure_id' => $closure->id,
                'event' => $event,
                'exception' => get_class($exception),
            ]);
        }
    }
}
