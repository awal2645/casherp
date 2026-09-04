<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\CompanyHubResource;
use App\CompanyHubSetting;
use App\Services\CompanyHubAccessService;
use App\Services\ReactWorkspaceContextService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CompanyHubResourceController extends Controller
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
        $canManage = $this->access->can($request->user(), 'company_hub.manage_documents', $businessId)
            || $this->access->can($request->user(), 'company_hub.manage_knowledge', $businessId);
        $resources = CompanyHubResource::where('business_id', $businessId)
            ->when(! $canManage, fn ($query) => $query->where('status', 'published'))
            ->with('owner:id,first_name,last_name,surname')->latest()->get()
            ->filter(fn ($resource) => $this->access->canView($resource, $request->user(), $businessId))->values();

        return response()->json(['data' => $this->formData($businessId) + compact('resources', 'canManage') + [
            'csrf_token' => csrf_token(),
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'permissions' => [
                'manage_knowledge' => $this->access->can($request->user(), 'company_hub.manage_knowledge', $businessId),
                'manage_documents' => $this->access->can($request->user(), 'company_hub.manage_documents', $businessId),
            ],
            'can_settings' => $this->access->can($request->user(), 'company_hub.manage_settings', $businessId),
        ]]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate($this->rules($businessId, $request));
        $permission = in_array($data['type'], ['knowledge', 'policy'], true)
            ? 'company_hub.manage_knowledge' : 'company_hub.manage_documents';
        $this->access->assertCan($request->user(), $permission, $businessId);
        $this->validateAudience($data, $businessId);

        $resource = DB::transaction(function () use ($data, $businessId, $request) {
            $resource = CompanyHubResource::create([
                'business_id' => $businessId,
                'type' => $data['type'],
                'category' => $data['category'] ?? null,
                'title' => $data['title'],
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'status' => $data['status'],
                'version' => 1,
                'audience_type' => $data['audience_type'],
                'audience_location_ids' => $data['audience_location_ids'] ?? null,
                'audience_department_ids' => $data['audience_department_ids'] ?? null,
                'audience_role_ids' => $data['audience_role_ids'] ?? null,
                'audience_user_ids' => $data['audience_user_ids'] ?? null,
                'effective_date' => $data['effective_date'] ?? null,
                'review_date' => $data['review_date'] ?? null,
                'owner_id' => $data['owner_id'] ?? $request->user()->id,
                'created_by' => $request->user()->id,
                'published_at' => $data['status'] === 'published' ? now() : null,
            ]);
            $this->saveFile($request, $resource, $businessId);

            return $resource;
        });
        $this->access->audit($businessId, $request->user()->id, 'resource.created', $resource, [], $resource->toArray());

        return $this->success($request, 'Internal resource saved.', ['resource_uuid' => $resource->uuid]);
    }

    public function revise(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $previous = CompanyHubResource::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        $permission = in_array($previous->type, ['knowledge', 'policy'], true)
            ? 'company_hub.manage_knowledge' : 'company_hub.manage_documents';
        $this->access->assertCan($request->user(), $permission, $businessId);
        $settings = CompanyHubSetting::firstOrCreate(['business_id' => $businessId]);
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'file' => ['nullable', 'file', 'max:'.((int) ($settings->max_attachment_mb ?: 10) * 1024), 'mimes:'.implode(',', config('company_hub.allowed_extensions'))],
            'review_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $resource = DB::transaction(function () use ($previous, $data, $businessId, $request) {
            $copy = $previous->replicate(['uuid', 'file_disk', 'file_path', 'original_filename', 'mime_type', 'file_size', 'deleted_at']);
            $copy->uuid = null;
            $copy->version = $previous->version + 1;
            $copy->previous_version_id = $previous->id;
            $copy->summary = $data['summary'] ?? $previous->summary;
            $copy->body = $data['body'] ?? $previous->body;
            $copy->status = $data['status'];
            $copy->review_date = $data['review_date'] ?? $previous->review_date;
            $copy->created_by = $request->user()->id;
            $copy->published_at = $data['status'] === 'published' ? now() : null;
            $copy->save();
            $this->saveFile($request, $copy, $businessId);
            if (! $request->hasFile('file') && $previous->file_path) {
                $copy->update($previous->only(['file_disk', 'file_path', 'original_filename', 'mime_type', 'file_size']));
            }
            $previous->update(['status' => 'archived']);

            return $copy;
        });
        $this->access->audit($businessId, $request->user()->id, 'resource.revised', $resource, $previous->toArray(), $resource->toArray());

        return $this->success($request, 'A new resource version was published.', ['resource_uuid' => $resource->uuid]);
    }

    public function download(Request $request, string $uuid)
    {
        $businessId = $this->businessId($request);
        $this->access->assertCan($request->user(), 'company_hub.view', $businessId);
        $resource = CompanyHubResource::where('business_id', $businessId)->where('uuid', $uuid)->firstOrFail();
        abort_unless($this->access->canView($resource, $request->user(), $businessId), 403);
        abort_unless($resource->file_disk === 'company_hub_private' && $resource->file_path
            && Storage::disk($resource->file_disk)->exists($resource->file_path), 404);
        $this->access->audit($businessId, $request->user()->id, 'resource.downloaded', $resource);

        return Storage::disk($resource->file_disk)->download($resource->file_path, $resource->original_filename);
    }

    private function saveFile(Request $request, CompanyHubResource $resource, int $businessId): void
    {
        if (! $request->hasFile('file')) {
            return;
        }
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, config('company_hub.allowed_extensions'), true), 422, 'This file type is not allowed.');
        $path = $file->storeAs(
            'business/'.$businessId.'/resources/'.$resource->uuid,
            bin2hex(random_bytes(20)).'.'.$extension,
            'company_hub_private'
        );
        $resource->update([
            'file_disk' => 'company_hub_private',
            'file_path' => $path,
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 190, ''),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    private function rules(int $businessId, Request $request): array
    {
        $settings = CompanyHubSetting::firstOrCreate(['business_id' => $businessId]);
        $maxKb = (int) ($settings->max_attachment_mb ?: 10) * 1024;

        return [
            'type' => ['required', Rule::in(['knowledge', 'policy', 'document', 'template'])],
            'category' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:191'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:50000', 'required_without:file'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', config('company_hub.allowed_extensions')), 'required_without:body'],
            'audience_type' => ['required', Rule::in(['company', 'locations', 'departments', 'roles', 'users'])],
            'audience_location_ids' => ['nullable', 'array'],
            'audience_location_ids.*' => [Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'audience_department_ids' => ['nullable', 'array'],
            'audience_department_ids.*' => [Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'audience_role_ids' => ['nullable', 'array'],
            'audience_role_ids.*' => [Rule::exists('roles', 'id')->where('business_id', $businessId)],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer'],
            'effective_date' => ['nullable', 'date'],
            'review_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'owner_id' => ['nullable', 'integer'],
        ];
    }

    private function validateAudience(array $data, int $businessId): void
    {
        $field = ['locations' => 'audience_location_ids', 'departments' => 'audience_department_ids', 'roles' => 'audience_role_ids', 'users' => 'audience_user_ids'][$data['audience_type']] ?? null;
        if ($field && empty($data[$field])) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Select at least one recipient.']);
        }
        $ids = collect(array_merge($data['audience_user_ids'] ?? [], isset($data['owner_id']) ? [$data['owner_id']] : []))->filter()->unique();
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
