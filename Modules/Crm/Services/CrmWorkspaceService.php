<?php

namespace Modules\Crm\Services;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Entities\Activity;
use Modules\Crm\Entities\Opportunity;
use Modules\Crm\Entities\Pipeline;
use Modules\Crm\Entities\PipelineStage;
use Modules\Crm\Entities\StageHistory;

class CrmWorkspaceService
{
    public function ensureDefaultPipeline(int $businessId, int $userId): Pipeline
    {
        return DB::transaction(function () use ($businessId, $userId) {
            $pipeline = Pipeline::where('business_id', $businessId)->where('is_default', true)->first();
            if (! $pipeline) {
                $pipeline = Pipeline::create([
                    'business_id' => $businessId,
                    'name' => 'Sales Pipeline',
                    'entity_type' => 'opportunity',
                    'is_default' => true,
                    'is_active' => true,
                    'created_by' => $userId,
                ]);
            }

            if ($pipeline->stages()->count() === 0) {
                $stages = [
                    ['new', 'New', '#64748b', 10, false, false],
                    ['qualified', 'Qualified', '#0ea5e9', 30, false, false],
                    ['proposal', 'Proposal', '#8b5cf6', 55, false, false],
                    ['negotiation', 'Negotiation', '#f59e0b', 75, false, false],
                    ['won', 'Won', '#10b981', 100, true, false],
                    ['lost', 'Lost', '#ef4444', 0, false, true],
                ];
                foreach ($stages as $position => $stage) {
                    PipelineStage::create([
                        'business_id' => $businessId,
                        'crm_pipeline_id' => $pipeline->id,
                        'code' => $stage[0],
                        'name' => $stage[1],
                        'color' => $stage[2],
                        'probability' => $stage[3],
                        'position' => $position,
                        'is_won' => $stage[4],
                        'is_lost' => $stage[5],
                    ]);
                }
            }

            return $pipeline->fresh('stages');
        });
    }

    public function scopeVisible(Builder $query, User $user, int $businessId): Builder
    {
        $query->where('crm_opportunities.business_id', $businessId);
        if ($this->can($user, 'crm.reports.view', $businessId)
            || $this->can($user, 'crm.pipeline.manage', $businessId)
            || $user->canForBusiness('crm.access_all_leads', $businessId)) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where('owner_id', $user->id)->orWhere('created_by', $user->id);
        });
    }

    public function move(Opportunity $opportunity, PipelineStage $stage, User $user, ?string $note = null): Opportunity
    {
        if ((int) $opportunity->business_id !== (int) $stage->business_id
            || (int) $opportunity->crm_pipeline_id !== (int) $stage->crm_pipeline_id) {
            throw ValidationException::withMessages(['stage_id' => 'The selected stage is outside this opportunity pipeline.']);
        }
        if ($stage->is_lost && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Record a lost reason before closing this opportunity.']);
        }

        return DB::transaction(function () use ($opportunity, $stage, $user, $note) {
            $locked = Opportunity::whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            $from = (int) $locked->crm_pipeline_stage_id;
            $status = $stage->is_won ? 'won' : ($stage->is_lost ? 'lost' : 'open');
            $locked->update([
                'crm_pipeline_stage_id' => $stage->id,
                'status' => $status,
                'won_at' => $stage->is_won ? now() : null,
                'lost_at' => $stage->is_lost ? now() : null,
                'lost_reason' => $stage->is_lost ? $note : null,
                'last_activity_at' => now(),
            ]);
            StageHistory::create([
                'business_id' => $locked->business_id,
                'crm_opportunity_id' => $locked->id,
                'from_stage_id' => $from ?: null,
                'to_stage_id' => $stage->id,
                'moved_by' => $user->id,
                'note' => $note,
                'moved_at' => now(),
            ]);

            return $locked->fresh(['stage', 'owner', 'contact']);
        });
    }

    public function refreshNextActivity(Opportunity $opportunity): void
    {
        $nextAt = Activity::where('crm_opportunity_id', $opportunity->id)
            ->where('status', 'planned')->whereNotNull('due_at')->min('due_at');
        $opportunity->update(['next_activity_at' => $nextAt]);
    }

    public function assertCan(User $user, string $permission, int $businessId): void
    {
        abort_unless($this->can($user, $permission, $businessId), 403, 'Unauthorized action.');
    }

    public function can(User $user, string $permission, int $businessId): bool
    {
        if ($user->can('superadmin') || $user->canForBusiness($permission, $businessId)) {
            return true;
        }

        $legacy = [
            'crm.workspace.view' => ['crm.access_all_leads', 'crm.access_own_leads'],
            'crm.opportunity.manage' => ['crm.access_all_leads', 'crm.access_own_leads'],
            'crm.activity.manage' => ['crm.access_all_schedule', 'crm.access_own_schedule'],
            'crm.reports.view' => ['crm.view_reports'],
        ];

        foreach ($legacy[$permission] ?? [] as $ability) {
            if ($user->canForBusiness($ability, $businessId)) {
                return true;
            }
        }

        return false;
    }

    public function uniqueCode(string $name): string
    {
        return Str::limit(Str::slug($name), 50, '').'-'.Str::lower(Str::random(6));
    }
}
