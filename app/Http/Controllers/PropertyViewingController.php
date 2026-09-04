<?php

namespace App\Http\Controllers;

use App\Business;
use App\Contact;
use App\Notifications\PropertyViewingStatusNotification;
use App\Property;
use App\PropertyUnit;
use App\PropertyViewingRequest;
use App\Services\PropertyAccessService;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyViewingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeView();
        $businessId = $this->businessId();
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'completed', 'cancelled'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = ! empty($data['from']) ? Carbon::parse($data['from'])->startOfDay() : now()->startOfMonth();
        $to = ! empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : now()->addMonth()->endOfMonth();
        $ability = $this->canManage() ? 'viewings' : 'view';
        $query = $this->scopePermitted(PropertyViewingRequest::query(), $ability)
            ->with(['property.businessLocation', 'unit', 'contact', 'assignee'])
            ->whereBetween('requested_start_at', [$from, $to])
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']));

        return view('property.viewings', [
            'viewings' => (clone $query)->orderBy('requested_start_at')->paginate(50)->withQueryString(),
            'calendar' => (clone $query)->whereIn('status', ['pending', 'approved'])->get()->groupBy(fn ($viewing) => $viewing->requested_start_at->toDateString()),
            'properties' => $this->permittedProperties($ability)->with('units')->orderBy('name')->get(),
            'contacts' => Contact::where('business_id', $businessId)->whereIn('type', ['customer', 'both'])->orderBy('name')->pluck('name', 'id'),
            'assignees' => User::forBusiness($businessId)->orderBy('first_name')->get()->pluck('user_full_name', 'id'),
            'from' => $from,
            'to' => $to,
            'canManage' => $this->canManage(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage();
        $businessId = $this->businessId();
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'property_unit_id' => ['nullable', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'requester_name' => ['required', 'string', 'max:191'],
            'requester_email' => ['nullable', 'email:rfc', 'max:191'],
            'requester_phone' => ['nullable', 'string', 'max:60'],
            'requested_start_at' => ['required', 'date'],
            'requested_end_at' => ['required', 'date', 'after:requested_start_at'],
            'assigned_to' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $property = $this->permittedProperties('viewings')->findOrFail($data['property_id']);
        if (! empty($data['property_unit_id'])) {
            PropertyUnit::where('property_id', $property->id)->findOrFail($data['property_unit_id']);
        }
        if (! empty($data['contact_id'])) {
            Contact::where('business_id', $businessId)->whereIn('type', ['customer', 'both'])->findOrFail($data['contact_id']);
        }
        if (! empty($data['assigned_to'])) {
            User::forBusiness($businessId)->findOrFail($data['assigned_to']);
        }

        $viewing = PropertyViewingRequest::create($data + [
            'uuid' => (string) Str::uuid(),
            'business_id' => $businessId,
            'status' => 'pending',
            'source' => 'staff',
        ]);
        $this->notify($viewing->load('property'), 'New property viewing request:');

        return redirect()->route('property.viewings.index')->with('status', ['success' => 1, 'msg' => 'Viewing request added for approval.']);
    }

    public function decide(Request $request, PropertyViewingRequest $viewing)
    {
        $this->authorizeManage();
        abort_unless((int) $viewing->business_id === $this->businessId(), 404);
        app(PropertyAccessService::class)->assertAccess(
            $viewing->property()->firstOrFail(),
            auth()->user(),
            $this->businessId(),
            'viewings'
        );
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'complete', 'cancel'])],
            'decision_note' => ['nullable', 'required_if:decision,reject,cancel', 'string', 'max:2000'],
        ]);
        $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed', 'cancel' => 'cancelled'];
        $allowed = [
            'pending' => ['approve', 'reject', 'cancel'],
            'approved' => ['complete', 'cancel'],
        ];

        $viewing = DB::transaction(function () use ($viewing, $data, $statusMap, $allowed) {
            $locked = PropertyViewingRequest::forBusiness($this->businessId())->whereKey($viewing->id)->lockForUpdate()->firstOrFail();
            if (! in_array($data['decision'], $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['decision' => 'This status transition is no longer allowed. Refresh the schedule.']);
            }
            if ($data['decision'] === 'approve') {
                $overlap = PropertyViewingRequest::forBusiness($this->businessId())
                    ->where('status', 'approved')->where('id', '!=', $locked->id)
                    ->where('property_id', $locked->property_id)
                    ->when($locked->property_unit_id, function ($query) use ($locked) {
                        $query->where(function ($unitQuery) use ($locked) {
                            $unitQuery->where('property_unit_id', $locked->property_unit_id)
                                ->orWhereNull('property_unit_id');
                        });
                    })
                    ->where('requested_start_at', '<', $locked->requested_end_at)
                    ->where('requested_end_at', '>', $locked->requested_start_at)->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['decision' => 'This property or unit already has an approved viewing in the requested time slot.']);
                }
            }
            $locked->update([
                'status' => $statusMap[$data['decision']],
                'decision_note' => $data['decision_note'] ?? null,
                'decided_by' => auth()->id(),
                'decided_at' => now(),
            ]);
            return $locked->fresh(['property']);
        }, 3);

        $this->notify($viewing, 'Property viewing '.$viewing->status.':');
        return redirect()->route('property.viewings.index')->with('status', ['success' => 1, 'msg' => 'Viewing request updated.']);
    }

    private function permittedProperties(string $ability = 'view')
    {
        return app(PropertyAccessService::class)
            ->scopeProperties(
                Property::query(),
                $this->businessId(),
                auth()->user(),
                $ability
            )
            ->where('properties.is_active', true);
    }

    private function scopePermitted($query, string $ability = 'view')
    {
        return $query->where('business_id', $this->businessId())->whereHas('property', function ($property) use ($ability) {
            app(PropertyAccessService::class)->scopeProperties(
                $property,
                $this->businessId(),
                auth()->user(),
                $ability
            );
        });
    }

    private function notify(PropertyViewingRequest $viewing, string $message): void
    {
        $business = Business::find($viewing->business_id);
        $recipients = User::whereIn('id', array_filter([$viewing->assigned_to, optional($business)->owner_id]))->get();
        Notification::send($recipients, new PropertyViewingStatusNotification($viewing, $message));
    }

    private function canManage(): bool
    {
        return auth()->user()->can('superadmin')
            || auth()->user()->hasRole('Admin#'.$this->businessId())
            || auth()->user()->canForBusiness('property.viewings.manage', $this->businessId());
    }

    private function authorizeView(): void
    {
        abort_unless(
            $this->canManage()
                || auth()->user()->canForBusiness('property.view', $this->businessId()),
            403
        );
    }

    private function authorizeManage(): void
    {
        abort_unless($this->canManage(), 403);
    }

    private function businessId(): int
    {
        return (int) request()->session()->get('user.business_id');
    }
}
