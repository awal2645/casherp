<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\CompanyHubChannel;
use App\CompanyHubComment;
use App\CompanyHubPost;
use App\CompanyHubPostAcknowledgement;
use App\CompanyHubSetting;
use App\Notifications\CompanyHubNotification;
use App\Services\CompanyHubAccessService;
use App\Services\FeatureAccessService;
use App\Services\PremiumModuleEntitlementService;
use App\Services\ReactWorkspaceContextService;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CompanyHubController extends Controller
{
    public function __construct(private CompanyHubAccessService $access)
    {
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        $this->access->ensureWorkspace($businessId, $request->user()->id);
        if (! $request->expectsJson()) {
            return response()->file(public_path('casherp-workspace.html'));
        }

        $channels = $this->access->visibleChannels($businessId, $request->user());
        $channelId = $request->integer('channel') ?: null;
        if ($channelId && ! $channels->contains('id', $channelId)) {
            abort(403, 'This channel is not available in the active company context.');
        }
        $posts = $this->access->visiblePosts($businessId, $request->user(), $channelId);
        $acknowledged = CompanyHubPostAcknowledgement::where('business_id', $businessId)
            ->where('user_id', $request->user()->id)->whereNotNull('acknowledged_at')
            ->pluck('company_hub_post_id')->map(fn ($id) => (int) $id)->all();
        $opened = CompanyHubPostAcknowledgement::where('business_id', $businessId)
            ->where('user_id', $request->user()->id)->whereNotNull('opened_at')
            ->pluck('company_hub_post_id')->map(fn ($id) => (int) $id)->all();

        $unacknowledgedCount = $posts->where('acknowledgement_required', true)
            ->reject(fn ($post) => in_array((int) $post->id, $acknowledged, true))->count();
        $settings = CompanyHubSetting::where('business_id', $businessId)->firstOrFail();

        $unreadAnnouncementCount = $posts->where('type', 'announcement')
            ->reject(fn ($post) => in_array((int) $post->id, $opened, true))->count();
        $posts = $posts->map(function ($post) use ($acknowledged, $opened) {
            $post->setAttribute('acknowledgement_count', $post->acknowledgements->whereNotNull('acknowledged_at')->count());
            $post->setAttribute('acknowledged_by_me', in_array((int) $post->id, $acknowledged, true));
            $post->setAttribute('opened_by_me', in_array((int) $post->id, $opened, true));
            // The React surface needs only the aggregate. Never expose stored
            // IP addresses or another employee's acknowledgement row.
            $post->unsetRelation('acknowledgements');

            return $post;
        });

        return response()->json(['data' => $this->formData($businessId) + [
            'channels' => $channels,
            'channel_id' => $channelId,
            'posts' => $posts,
            'unacknowledged_count' => $unacknowledgedCount,
            'unread_announcement_count' => $unreadAnnouncementCount,
            'settings' => $settings,
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'permissions' => $this->permissions($request, $businessId),
            'integrations' => [
                'project_tasks' => app(FeatureAccessService::class)->enabled('projects', $businessId)
                    && ($request->user()->can('superadmin') || (bool) (new ModuleUtil())->hasThePermissionInSubscription($businessId, 'project_module'))
                        ? url('/project/project-task') : null,
            ],
        ]]);
    }

    public function storePost(Request $request, PremiumModuleEntitlementService $entitlements)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.post', $businessId);
        $data = $request->validate($this->postRules($businessId));
        if ($data['type'] === 'announcement') {
            $this->access->assertCan($request->user(), 'company_hub.publish_announcements', $businessId);
        }
        $this->validateAudience($data);
        if (! empty($data['channel_id']) && ! $this->access->visibleChannels($businessId, $request->user())->contains('id', (int) $data['channel_id'])) {
            abort(403, 'You cannot publish to this channel.');
        }

        $post = DB::transaction(function () use ($data, $businessId, $request, $entitlements) {
            $entitlements->consume($businessId, 'company_hub');
            $settings = CompanyHubSetting::firstOrCreate(['business_id' => $businessId]);

            return CompanyHubPost::create([
                'business_id' => $businessId,
                'company_hub_channel_id' => $data['channel_id'] ?? null,
                'author_id' => $request->user()->id,
                'type' => $data['type'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'priority' => $data['priority'],
                'audience_type' => $data['audience_type'],
                'audience_location_ids' => $data['audience_location_ids'] ?? null,
                'audience_department_ids' => $data['audience_department_ids'] ?? null,
                'audience_role_ids' => $data['audience_role_ids'] ?? null,
                'audience_user_ids' => $data['audience_user_ids'] ?? null,
                'comments_enabled' => $request->boolean('comments_enabled', (bool) $settings->comments_enabled),
                'acknowledgement_required' => $request->boolean('acknowledgement_required'),
                'is_pinned' => $request->boolean('is_pinned')
                    && $this->access->can($request->user(), 'company_hub.publish_announcements', $businessId),
                'published_at' => $data['published_at'] ?? now(),
                'expires_at' => $data['expires_at'] ?? null,
            ]);
        });

        $this->access->audit($businessId, $request->user()->id, 'post.created', $post, [], $post->toArray());
        if ($post->type === 'announcement' || in_array($post->priority, ['important', 'urgent'], true)) {
            $recipients = $this->access->recipients($post, $businessId)->reject(fn ($user) => $user->id === $request->user()->id);
            $emailEnabled = $post->type === 'announcement'
                && (bool) CompanyHubSetting::where('business_id', $businessId)->value('email_important_announcements');
            Notification::send($recipients, new CompanyHubNotification($post, $emailEnabled));
        }

        return $this->success($request, 'Company Hub post published.', ['post_uuid' => $post->uuid], route('company-hub.index', ['post' => $post->uuid]));
    }

    public function acknowledge(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        $post = CompanyHubPost::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canView($post, $request->user(), $businessId), 403);

        $ack = CompanyHubPostAcknowledgement::firstOrNew([
            'business_id' => $businessId,
            'company_hub_post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);
        $ack->opened_at = $ack->opened_at ?: now();
        $ack->acknowledged_at = now();
        $ack->ip_address = $request->ip();
        $ack->save();
        $this->access->audit($businessId, $request->user()->id, 'post.acknowledged', $post);

        return $this->success($request, 'Acknowledgement recorded.');
    }

    public function markOpened(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        $post = CompanyHubPost::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canView($post, $request->user(), $businessId), 403);
        $ack = CompanyHubPostAcknowledgement::firstOrNew([
            'business_id' => $businessId,
            'company_hub_post_id' => $post->id,
            'user_id' => $request->user()->id,
        ]);
        if (! $ack->opened_at) {
            $ack->opened_at = now();
            $ack->ip_address = $request->ip();
            $ack->save();
        }

        return $this->success($request, 'Post marked as read.');
    }

    public function storeComment(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        $this->access->assertCan($request->user(), 'company_hub.comment', $businessId);
        $post = CompanyHubPost::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canView($post, $request->user(), $businessId) && $post->comments_enabled, 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('company_hub_comments', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('company_hub_post_id', $post->id)),
            ],
        ]);
        $comment = CompanyHubComment::create([
            'business_id' => $businessId,
            'company_hub_post_id' => $post->id,
            'parent_id' => $data['parent_id'] ?? null,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $this->access->audit($businessId, $request->user()->id, 'comment.created', $comment);

        return $this->success($request, 'Comment added.', ['comment' => $comment]);
    }

    public function storeChannel(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_channels', $businessId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['open', 'private', 'announcement'])],
            'business_location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => [Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [Rule::exists('users', 'id')],
        ]);
        $this->assertBusinessUsers($data['member_ids'] ?? [], $businessId);
        $base = Str::slug($data['name']) ?: 'channel';
        $slug = $base;
        $suffix = 2;
        while (CompanyHubChannel::where('business_id', $businessId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $channel = DB::transaction(function () use ($data, $businessId, $request, $slug) {
            $channel = CompanyHubChannel::create([
                'business_id' => $businessId,
                'business_location_id' => $data['business_location_id'] ?? null,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'department_ids' => $data['department_ids'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $memberIds = collect($data['member_ids'] ?? [])->push($request->user()->id)->unique();
            foreach ($memberIds as $userId) {
                DB::table('company_hub_channel_members')->insertOrIgnore([
                    'business_id' => $businessId,
                    'company_hub_channel_id' => $channel->id,
                    'user_id' => $userId,
                    'role' => (int) $userId === (int) $request->user()->id ? 'owner' : 'member',
                    'added_by' => $request->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $channel;
        });
        $this->access->audit($businessId, $request->user()->id, 'channel.created', $channel);

        return $this->success($request, 'Channel created.', ['channel_id' => $channel->id], route('company-hub.index', ['channel' => $channel->id]));
    }

    public function archiveChannel(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_channels', $businessId);
        $channel = CompanyHubChannel::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        $channel->update(['is_archived' => true]);
        $this->access->audit($businessId, $request->user()->id, 'channel.archived', $channel);

        return $this->success($request, 'Channel archived.', [], route('company-hub.index'));
    }

    public function directory(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        if (! $request->expectsJson()) {
            return response()->file(public_path('casherp-workspace.html'));
        }
        $users = User::forBusiness($businessId)->where('allow_login', 1)
            ->orderBy('first_name')->paginate(30, [
                'users.id', 'users.surname', 'users.first_name', 'users.last_name',
                'users.email', 'users.contact_number', 'users.essentials_department_id',
            ]);
        $departments = Category::where('business_id', $businessId)->where('category_type', 'hrm_department')->pluck('name', 'id');

        return response()->json(['data' => compact('users', 'departments') + [
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'can_settings' => $this->access->can($request->user(), 'company_hub.manage_settings', $businessId),
        ]]);
    }

    public function settings(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_settings', $businessId);
        $this->access->ensureWorkspace($businessId, $request->user()->id);
        if (! $request->expectsJson()) {
            return response()->file(public_path('casherp-workspace.html'));
        }

        return response()->json(['data' => [
            'settings' => CompanyHubSetting::where('business_id', $businessId)->firstOrFail(),
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
        ]]);
    }

    public function updateSettings(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.manage_settings', $businessId);
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'between:30,3650'],
            'max_attachment_mb' => ['required', 'integer', 'between:1,25'],
        ]);
        $settings = CompanyHubSetting::where('business_id', $businessId)->firstOrFail();
        $before = $settings->toArray();
        $settings->update($data + [
            'comments_enabled' => $request->boolean('comments_enabled'),
            'email_important_announcements' => $request->boolean('email_important_announcements'),
        ]);
        $this->access->audit($businessId, $request->user()->id, 'settings.updated', $settings, $before, $settings->toArray());

        return $this->success($request, 'Company Hub settings updated.', ['settings' => $settings]);
    }

    private function businessId(Request $request): int
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);

        return $businessId;
    }

    private function postRules(int $businessId): array
    {
        return [
            'channel_id' => ['nullable', Rule::exists('company_hub_channels', 'id')->where('business_id', $businessId)->where('is_archived', false)],
            'type' => ['required', Rule::in(['discussion', 'announcement', 'recognition'])],
            'title' => ['nullable', 'required_if:type,announcement', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'audience_type' => ['required', Rule::in(['company', 'locations', 'departments', 'roles', 'users'])],
            'audience_location_ids' => ['nullable', 'array'],
            'audience_location_ids.*' => [Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'audience_department_ids' => ['nullable', 'array'],
            'audience_department_ids.*' => [Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'audience_role_ids' => ['nullable', 'array'],
            'audience_role_ids.*' => [Rule::exists('roles', 'id')->where('business_id', $businessId)],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ];
    }

    private function validateAudience(array $data): void
    {
        $field = [
            'locations' => 'audience_location_ids', 'departments' => 'audience_department_ids',
            'roles' => 'audience_role_ids', 'users' => 'audience_user_ids',
        ][$data['audience_type']] ?? null;
        if ($field && empty($data[$field])) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Select at least one recipient for this audience.']);
        }
        if ($data['audience_type'] === 'users') {
            $this->assertBusinessUsers($data['audience_user_ids'] ?? [], (int) session('user.business_id'));
        }
    }

    private function assertBusinessUsers(array $ids, int $businessId): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique();
        if ($ids->isNotEmpty() && User::forBusiness($businessId)->whereIn('users.id', $ids)->distinct()->count('users.id') !== $ids->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['audience_user_ids' => 'One or more selected users do not belong to the active company.']);
        }
    }

    private function formData(int $businessId): array
    {
        return [
            'locations' => BusinessLocation::where('business_id', $businessId)->active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Category::where('business_id', $businessId)->where('category_type', 'hrm_department')->orderBy('name')->pluck('name', 'id'),
            'roles' => Role::where('business_id', $businessId)->orderBy('name')->get()->mapWithKeys(fn ($role) => [$role->id => str_replace('#'.$businessId, '', $role->name)]),
            'companyUsers' => User::forBusiness($businessId)->where('allow_login', 1)->orderBy('first_name')->get(['users.id', 'users.first_name', 'users.last_name', 'users.surname'])->mapWithKeys(fn ($user) => [$user->id => trim($user->first_name.' '.$user->last_name)]),
        ];
    }

    private function permissions(Request $request, int $businessId): array
    {
        return collect([
            'post', 'comment', 'publish_announcements', 'manage_channels', 'manage_knowledge',
            'manage_documents', 'manage_events', 'manage_settings', 'view_acknowledgements', 'moderate',
        ])->mapWithKeys(fn ($ability) => [$ability => $this->access->can($request->user(), 'company_hub.'.$ability, $businessId)])->all();
    }

    private function success(Request $request, string $message, array $data = [], ?string $redirect = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'data' => $data]);
        }

        return redirect($redirect ?: url()->previous())->with('status', ['success' => 1, 'msg' => $message]);
    }
}
