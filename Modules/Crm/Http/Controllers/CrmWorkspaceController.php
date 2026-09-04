<?php

namespace Modules\Crm\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Http\Controllers\Controller;
use App\Services\PremiumModuleEntitlementService;
use App\Services\ReactWorkspaceContextService;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Crm\Entities\Activity;
use Modules\Crm\Entities\Opportunity;
use Modules\Crm\Entities\Pipeline;
use Modules\Crm\Entities\PipelineStage;
use Modules\Crm\Services\CrmWorkspaceService;

class CrmWorkspaceController extends Controller
{
    public function __construct(private CrmWorkspaceService $workspace)
    {
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.workspace.view', $businessId);
        $defaultPipeline = $this->workspace->ensureDefaultPipeline($businessId, $request->user()->id);
        if (! $request->expectsJson()) {
            return response()->file(public_path('casherp-workspace.html'));
        }
        $pipelines = Pipeline::where('business_id', $businessId)->where('is_active', true)->with('stages')->orderByDesc('is_default')->get();
        $pipeline = $pipelines->firstWhere('id', $request->integer('pipeline')) ?: $defaultPipeline;
        $opportunities = $this->workspace->scopeVisible(
            Opportunity::query()->with(['stage', 'contact:id,name,supplier_business_name,mobile,email', 'owner:id,first_name,last_name,surname']),
            $request->user(),
            $businessId
        )->where('crm_pipeline_id', $pipeline->id)->latest()->get();

        $visibleOpportunityIds = $opportunities->pluck('id');
        $activities = Activity::where('business_id', $businessId)
            ->whereIn('crm_opportunity_id', $visibleOpportunityIds)
            ->where('status', 'planned')->with(['owner:id,first_name,last_name,surname', 'opportunity:id,title'])
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->limit(20)->get();
        $open = $opportunities->where('status', 'open');
        $metrics = [
            'open_count' => $open->count(),
            'pipeline_value' => $open->sum('estimated_value'),
            'weighted_value' => $open->sum(fn ($opportunity) => (float) $opportunity->estimated_value * ((int) $opportunity->stage->probability / 100)),
            'overdue_activities' => $activities->where('due_at', '<', now())->count(),
            'won_count' => $opportunities->where('status', 'won')->count(),
        ];
        $businessUsers = User::forBusiness($businessId)
            ->where('allow_login', 1)
            ->get(['users.id', 'users.first_name', 'users.last_name']);
        $names = fn ($users) => $users->mapWithKeys(
            fn ($user) => [$user->id => trim($user->first_name.' '.$user->last_name)]
        );

        return response()->json(['data' => [
            'pipelines' => $pipelines,
            'pipeline' => $pipeline,
            'opportunities' => $opportunities,
            'activities' => $activities,
            'metrics' => $metrics,
            'contacts' => Contact::where('business_id', $businessId)->whereIn('type', ['lead', 'customer', 'both'])->active()->orderBy('name')->pluck('name', 'id'),
            'opportunity_users' => $names($this->canAssignOpportunities($request->user(), $businessId)
                ? $businessUsers
                : $businessUsers->where('id', $request->user()->id)),
            'activity_users' => $names($this->canAssignActivities($request->user(), $businessId)
                ? $businessUsers
                : $businessUsers->where('id', $request->user()->id)),
            'locations' => BusinessLocation::where('business_id', $businessId)->active()->orderBy('name')->pluck('name', 'id'),
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'permissions' => [
                'manage_pipeline' => $this->workspace->can($request->user(), 'crm.pipeline.manage', $businessId),
                'manage_opportunities' => $this->workspace->can($request->user(), 'crm.opportunity.manage', $businessId),
                'manage_activities' => $this->workspace->can($request->user(), 'crm.activity.manage', $businessId),
                'view_reports' => $this->workspace->can($request->user(), 'crm.reports.view', $businessId),
            ],
        ]]);
    }

    public function storePipeline(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.pipeline.manage', $businessId);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('crm_pipelines')->where('business_id', $businessId)]]);
        $pipeline = Pipeline::create($data + [
            'business_id' => $businessId,
            'entity_type' => 'opportunity',
            'is_default' => ! Pipeline::where('business_id', $businessId)->exists(),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        foreach ([['New', 10, '#64748b'], ['Qualified', 30, '#0ea5e9'], ['Proposal', 55, '#8b5cf6'], ['Won', 100, '#10b981'], ['Lost', 0, '#ef4444']] as $position => $stage) {
            PipelineStage::create([
                'business_id' => $businessId, 'crm_pipeline_id' => $pipeline->id,
                'code' => $this->workspace->uniqueCode($stage[0]), 'name' => $stage[0],
                'probability' => $stage[1], 'color' => $stage[2], 'position' => $position,
                'is_won' => $stage[0] === 'Won', 'is_lost' => $stage[0] === 'Lost',
            ]);
        }

        return $this->success($request, 'Pipeline created.', ['pipeline_id' => $pipeline->id], route('crm.workspace', ['pipeline' => $pipeline->id]));
    }

    public function storeStage(Request $request, int $pipelineId)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.pipeline.manage', $businessId);
        $pipeline = Pipeline::where('business_id', $businessId)->findOrFail($pipelineId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'probability' => ['required', 'integer', 'between:0,100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'outcome' => ['required', Rule::in(['open', 'won', 'lost'])],
        ]);
        PipelineStage::create([
            'business_id' => $businessId,
            'crm_pipeline_id' => $pipeline->id,
            'code' => $this->workspace->uniqueCode($data['name']),
            'name' => $data['name'], 'probability' => $data['probability'], 'color' => $data['color'],
            'position' => ((int) $pipeline->stages()->max('position')) + 1,
            'is_won' => $data['outcome'] === 'won', 'is_lost' => $data['outcome'] === 'lost',
        ]);

        return $this->success($request, 'Pipeline stage added.');
    }

    public function storeOpportunity(Request $request, PremiumModuleEntitlementService $entitlements)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.opportunity.manage', $businessId);
        $data = $request->validate($this->opportunityRules($businessId));
        $this->assertBusinessUser((int) $data['owner_id'], $businessId);
        abort_if(! $this->canAssignOpportunities($request->user(), $businessId)
            && (int) $data['owner_id'] !== (int) $request->user()->id, 403, 'You may only assign opportunities to yourself.');
        $stage = PipelineStage::where('business_id', $businessId)->where('crm_pipeline_id', $data['pipeline_id'])->findOrFail($data['stage_id']);
        if ($stage->is_won || $stage->is_lost) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'stage_id' => 'A new opportunity must start in an open pipeline stage.',
            ]);
        }
        $openCount = Opportunity::where('business_id', $businessId)->where('status', 'open')->count();
        $entitlements->assertCapacity($businessId, 'crm_pipeline', $openCount);
        $opportunity = Opportunity::create([
            'business_id' => $businessId,
            'crm_pipeline_id' => $data['pipeline_id'],
            'crm_pipeline_stage_id' => $stage->id,
            'contact_id' => $data['contact_id'] ?? null,
            'business_location_id' => $data['business_location_id'] ?? null,
            'owner_id' => $data['owner_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? 0,
            'currency_code' => ! empty($data['currency_code']) ? strtoupper($data['currency_code']) : null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source' => $data['source'] ?? null,
            'status' => 'open',
            'won_at' => null,
            'lost_at' => null,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($request, 'Opportunity created.', ['opportunity_uuid' => $opportunity->uuid], route('crm.workspace', ['pipeline' => $opportunity->crm_pipeline_id]));
    }

    public function updateOpportunity(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.opportunity.manage', $businessId);
        $opportunity = $this->visibleOpportunity($uuid, $businessId, $request->user());
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:10000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'business_location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
        ]);
        $this->assertBusinessUser((int) $data['owner_id'], $businessId);
        abort_if(! $this->canAssignOpportunities($request->user(), $businessId)
            && (int) $data['owner_id'] !== (int) $request->user()->id, 403, 'You may only assign opportunities to yourself.');
        $opportunity->update(array_merge($data, ['currency_code' => ! empty($data['currency_code']) ? strtoupper($data['currency_code']) : null]));

        return $this->success($request, 'Opportunity updated.');
    }

    public function moveOpportunity(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.opportunity.manage', $businessId);
        $opportunity = $this->visibleOpportunity($uuid, $businessId, $request->user());
        $data = $request->validate(['stage_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:2000']]);
        $stage = PipelineStage::where('business_id', $businessId)->findOrFail($data['stage_id']);
        $this->workspace->move($opportunity, $stage, $request->user(), $data['note'] ?? null);

        return $this->success($request, 'Opportunity stage updated.');
    }

    public function archiveOpportunity(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.opportunity.manage', $businessId);
        $opportunity = $this->visibleOpportunity($uuid, $businessId, $request->user());
        $opportunity->delete();

        return $this->success($request, 'Opportunity archived.');
    }

    public function storeActivity(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.activity.manage', $businessId);
        $opportunity = $this->visibleOpportunity($uuid, $businessId, $request->user());
        $data = $request->validate([
            'type' => ['required', Rule::in(['task', 'call', 'email', 'meeting', 'note'])],
            'direction' => ['required', Rule::in(['internal', 'inbound', 'outbound'])],
            'subject' => ['required', 'string', 'max:191'],
            'details' => ['nullable', 'string', 'max:10000'],
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'due_at' => ['nullable', 'date'],
            'remind_at' => ['nullable', 'date', 'before_or_equal:due_at'],
        ]);
        $this->assertBusinessUser((int) $data['owner_id'], $businessId);
        abort_if(! $this->canAssignActivities($request->user(), $businessId)
            && (int) $data['owner_id'] !== (int) $request->user()->id, 403, 'You may only assign activities to yourself.');
        Activity::create($data + [
            'business_id' => $businessId,
            'crm_opportunity_id' => $opportunity->id,
            'contact_id' => $opportunity->contact_id,
            'status' => $data['type'] === 'note' ? 'completed' : 'planned',
            'completed_at' => $data['type'] === 'note' ? now() : null,
            'completed_by' => $data['type'] === 'note' ? $request->user()->id : null,
            'created_by' => $request->user()->id,
        ]);
        $opportunity->update(['last_activity_at' => now()]);
        $this->workspace->refreshNextActivity($opportunity);

        return $this->success($request, 'CRM activity recorded.');
    }

    public function completeActivity(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->assertSubscription($businessId, $request->user());
        $this->workspace->assertCan($request->user(), 'crm.activity.manage', $businessId);
        $activity = Activity::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        abort_if(! $this->canAssignActivities($request->user(), $businessId)
            && (int) $activity->owner_id !== (int) $request->user()->id
            && (int) $activity->created_by !== (int) $request->user()->id, 403, 'You may only complete your own activities.');
        $opportunity = $this->visibleOpportunity($activity->opportunity->uuid, $businessId, $request->user());
        $data = $request->validate(['outcome' => ['nullable', 'string', 'max:1000']]);
        $activity->update(['status' => 'completed', 'outcome' => $data['outcome'] ?? null, 'completed_at' => now(), 'completed_by' => $request->user()->id]);
        $opportunity->update(['last_activity_at' => now()]);
        $this->workspace->refreshNextActivity($opportunity);

        return $this->success($request, 'Activity completed.');
    }

    private function opportunityRules(int $businessId): array
    {
        return [
            'pipeline_id' => ['required', Rule::exists('crm_pipelines', 'id')->where('business_id', $businessId)->where('is_active', true)],
            'stage_id' => ['required', 'integer'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->whereIn('type', ['lead', 'customer', 'both']))],
            'business_location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'owner_id' => ['required', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:10000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function visibleOpportunity(string $uuid, int $businessId, User $user): Opportunity
    {
        return $this->workspace->scopeVisible(Opportunity::query(), $user, $businessId)->where('uuid', $uuid)->firstOrFail();
    }

    private function assertBusinessUser(int $userId, int $businessId): void
    {
        if (! User::forBusiness($businessId)->where('users.id', $userId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['owner_id' => 'The owner must belong to the active company.']);
        }
    }

    private function canAssignOpportunities(User $user, int $businessId): bool
    {
        return $user->can('superadmin')
            || $user->canForBusiness('crm.opportunity.manage', $businessId)
            || $user->canForBusiness('crm.pipeline.manage', $businessId)
            || $user->canForBusiness('crm.access_all_leads', $businessId);
    }

    private function canAssignActivities(User $user, int $businessId): bool
    {
        return $user->can('superadmin')
            || $user->canForBusiness('crm.activity.manage', $businessId)
            || $user->canForBusiness('crm.access_all_schedule', $businessId);
    }

    private function assertSubscription(int $businessId, User $user): void
    {
        $enabled = (new ModuleUtil())->hasThePermissionInSubscription($businessId, 'crm_module');
        abort_unless($user->can('superadmin') || $enabled, 403, 'CRM is not enabled in this subscription.');
    }

    private function businessId(Request $request): int
    {
        $id = (int) $request->session()->get('user.business_id');
        abort_if($id < 1 || ! $request->user()->canAccessBusiness($id), 403);

        return $id;
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $data]);
        }

        return redirect($redirect ?: url()->previous())->with('status', ['success' => 1, 'msg' => $message]);
    }
}
