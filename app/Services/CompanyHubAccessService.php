<?php

namespace App\Services;

use App\CompanyHubAuditEvent;
use App\CompanyHubChannel;
use App\CompanyHubPost;
use App\CompanyHubSetting;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyHubAccessService
{
    public function ensureWorkspace(int $businessId, int $userId): void
    {
        CompanyHubSetting::firstOrCreate(
            ['business_id' => $businessId],
            [
                'comments_enabled' => true,
                'retention_days' => 1095,
                'max_attachment_mb' => config('company_hub.default_max_attachment_mb', 10),
                'email_important_announcements' => false,
            ]
        );

        foreach (config('company_hub.default_channels', []) as $channel) {
            CompanyHubChannel::firstOrCreate(
                ['business_id' => $businessId, 'slug' => $channel['slug']],
                $channel + ['created_by' => $userId]
            );
        }
    }

    public function visiblePosts(int $businessId, User $user, ?int $channelId = null): Collection
    {
        $query = CompanyHubPost::query()
            ->where('business_id', $businessId)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($channelId, fn ($query) => $query->where('company_hub_channel_id', $channelId))
            ->with(['author:id,first_name,last_name,surname', 'channel:id,name', 'comments.user:id,first_name,last_name,surname', 'acknowledgements'])
            ->orderByDesc('is_pinned')->latest('published_at')->limit(250);

        return $query->get()->filter(fn ($post) => $this->canView($post, $user, $businessId))->values();
    }

    public function visibleChannels(int $businessId, User $user): Collection
    {
        return CompanyHubChannel::query()
            ->where('business_id', $businessId)->where('is_archived', false)
            ->withCount('posts')->orderBy('name')->get()
            ->filter(function (CompanyHubChannel $channel) use ($user, $businessId) {
                if ($channel->type === 'private') {
                    return $channel->members()->where('users.id', $user->id)->exists()
                        || $this->can($user, 'company_hub.manage_channels', $businessId);
                }

                if ($channel->business_location_id && ! in_array((int) $channel->business_location_id, $this->locationIds($user, $businessId), true)) {
                    return false;
                }

                $departments = array_map('intval', $channel->department_ids ?: []);

                return empty($departments) || in_array($this->departmentId($user, $businessId), $departments, true);
            })->values();
    }

    public function canView(Model $item, User $user, int $businessId): bool
    {
        if ((int) $item->business_id !== $businessId || ! $user->canAccessBusiness($businessId)) {
            return false;
        }

        if ($item instanceof CompanyHubPost && $item->company_hub_channel_id) {
            $channel = $item->relationLoaded('channel') ? $item->channel : $item->channel()->first();
            if ($channel && $channel->type === 'private'
                && ! $channel->members()->where('users.id', $user->id)->exists()
                && ! $this->can($user, 'company_hub.manage_channels', $businessId)) {
                return false;
            }
        }

        if ($this->can($user, 'company_hub.moderate', $businessId) || (int) ($item->author_id ?? $item->owner_id ?? 0) === (int) $user->id) {
            return true;
        }

        $type = $item->audience_type ?: 'company';
        if ($type === 'company') {
            return true;
        }

        if ($type === 'users') {
            return in_array((int) $user->id, array_map('intval', $item->audience_user_ids ?: []), true);
        }
        if ($type === 'departments') {
            return in_array($this->departmentId($user, $businessId), array_map('intval', $item->audience_department_ids ?: []), true);
        }
        if ($type === 'roles') {
            return ! empty(array_intersect($this->roleIds($user, $businessId), array_map('intval', $item->audience_role_ids ?: [])));
        }
        if ($type === 'locations') {
            return ! empty(array_intersect($this->locationIds($user, $businessId), array_map('intval', $item->audience_location_ids ?: [])));
        }

        return false;
    }

    public function recipients(Model $item, int $businessId): Collection
    {
        return User::forBusiness($businessId)->where('allow_login', 1)->get()
            ->filter(fn (User $user) => $this->can($user, 'company_hub.view', $businessId)
                && $this->canView($item, $user, $businessId));
    }

    public function can(User $user, string $permission, int $businessId): bool
    {
        return $user->can('superadmin') || $user->canForBusiness($permission, $businessId);
    }

    public function assertCan(User $user, string $permission, int $businessId): void
    {
        abort_unless($this->can($user, $permission, $businessId), 403, 'Unauthorized action.');
    }

    public function audit(int $businessId, ?int $actorId, string $event, Model $model, array $before = [], array $after = []): void
    {
        CompanyHubAuditEvent::create([
            'business_id' => $businessId,
            'actor_id' => $actorId,
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    private function departmentId(User $user, int $businessId): int
    {
        $profile = $user->employmentProfileFor($businessId);

        return (int) ($profile->department_id ?? $user->essentials_department_id ?? 0);
    }

    private function roleIds(User $user, int $businessId): array
    {
        return $user->allBusinessRoles()->where('roles.business_id', $businessId)->pluck('roles.id')->map(fn ($id) => (int) $id)->all();
    }

    private function locationIds(User $user, int $businessId): array
    {
        $ids = $user->permitted_locations($businessId);
        if ($ids === 'all') {
            return DB::table('business_locations')->where('business_id', $businessId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return array_map('intval', $ids ?: []);
    }
}
