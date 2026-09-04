<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\CompanyHubEvent;
use App\Services\CompanyHubAccessService;
use App\Services\ReactWorkspaceContextService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CompanyHubEventController extends Controller
{
    public function __construct(private CompanyHubAccessService $access)
    {
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        if (! $request->expectsJson()) {
            return response()->file(public_path('casherp-workspace.html'));
        }
        $events = CompanyHubEvent::where('business_id', $businessId)->where('status', 'scheduled')
            ->where('ends_at', '>=', now())->with('owner:id,first_name,last_name,surname')
            ->orderBy('starts_at')->get()
            ->filter(fn ($event) => $this->access->canView($event, $request->user(), $businessId))->values();

        return response()->json(['data' => $this->formData($businessId) + compact('events') + [
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'can_manage' => $this->access->can($request->user(), 'company_hub.manage_events', $businessId),
            'can_settings' => $this->access->can($request->user(), 'company_hub.manage_settings', $businessId),
        ]]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_events', $businessId);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone'],
            'location_text' => ['nullable', 'string', 'max:191'],
            'business_location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'audience_type' => ['required', Rule::in(['company', 'locations', 'departments', 'roles', 'users'])],
            'audience_location_ids' => ['nullable', 'array'],
            'audience_location_ids.*' => [Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'audience_department_ids' => ['nullable', 'array'],
            'audience_department_ids.*' => [Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'audience_role_ids' => ['nullable', 'array'],
            'audience_role_ids.*' => [Rule::exists('roles', 'id')->where('business_id', $businessId)],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer'],
        ]);
        $this->validateAudience($data, $businessId);
        $event = CompanyHubEvent::create($data + [
            'business_id' => $businessId,
            'owner_id' => $request->user()->id,
            'status' => 'scheduled',
        ]);
        $this->access->audit($businessId, $request->user()->id, 'event.created', $event, [], $event->toArray());

        return $this->success($request, 'Internal event scheduled.', ['event_uuid' => $event->uuid]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_events', $businessId);
        $event = CompanyHubEvent::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        $event->update(['status' => 'cancelled']);
        $this->access->audit($businessId, $request->user()->id, 'event.cancelled', $event);

        return $this->success($request, 'Event cancelled.');
    }

    private function validateAudience(array $data, int $businessId): void
    {
        $field = ['locations' => 'audience_location_ids', 'departments' => 'audience_department_ids', 'roles' => 'audience_role_ids', 'users' => 'audience_user_ids'][$data['audience_type']] ?? null;
        if ($field && empty($data[$field])) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Select at least one recipient.']);
        }
        $ids = collect($data['audience_user_ids'] ?? [])->filter()->unique();
        if ($ids->isNotEmpty() && User::forBusiness($businessId)->whereIn('users.id', $ids)->distinct()->count('users.id') !== $ids->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['audience_user_ids' => 'A selected user is outside the active company.']);
        }
    }

    private function businessId(Request $request): int
    {
        $id = (int) $request->session()->get('user.business_id');
        abort_if($id < 1 || ! $request->user()->canAccessBusiness($id), 403);

        return $id;
    }

    private function formData(int $businessId): array
    {
        return [
            'locations' => BusinessLocation::where('business_id', $businessId)->active()->pluck('name', 'id'),
            'departments' => Category::where('business_id', $businessId)->where('category_type', 'hrm_department')->pluck('name', 'id'),
            'roles' => Role::where('business_id', $businessId)->pluck('name', 'id')->map(fn ($name) => str_replace('#'.$businessId, '', $name)),
            'companyUsers' => User::forBusiness($businessId)->where('allow_login', 1)->get(['users.id', 'users.first_name', 'users.last_name'])->mapWithKeys(fn ($user) => [$user->id => trim($user->first_name.' '.$user->last_name)]),
        ];
    }

    private function success(Request $request, string $message, array $data = [])
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $data]);
        }

        return back()->with('status', ['success' => 1, 'msg' => $message]);
    }
}
