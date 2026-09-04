<?php

namespace App\Services;

use App\Business;
use App\BusinessDocumentSetting;
use App\DocumentType;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessDocumentAccessService
{
    private array $subjectContextCache = [];

    public function permittedTypes(Business $business, User $user, Collection $types): Collection
    {
        if ($this->isBusinessAdmin($user, $business->id)) {
            return $types->values();
        }

        $settings = BusinessDocumentSetting::where('business_id', $business->id)
            ->whereIn('document_type_id', $types->pluck('id'))
            ->get()
            ->keyBy('document_type_id');

        return $types->filter(function (DocumentType $type) use ($business, $user, $settings) {
            return $this->canUseType($business, $user, $type, $settings->get($type->id));
        })->values();
    }

    public function assertTypeAccess(Business $business, User $user, DocumentType $type): void
    {
        $setting = BusinessDocumentSetting::where('business_id', $business->id)
            ->where('document_type_id', $type->id)
            ->first();

        abort_unless($this->canUseType($business, $user, $type, $setting), 403, 'You are not authorized to use this document type.');
    }

    public function canUseType(
        Business $business,
        User $user,
        DocumentType $type,
        ?BusinessDocumentSetting $setting = null
    ): bool {
        if ($this->isBusinessAdmin($user, $business->id)) {
            return true;
        }

        $access = (array) data_get(optional($setting)->settings, 'access', []);
        $userIds = $this->ids($access['user_ids'] ?? []);
        $roleIds = $this->ids($access['role_ids'] ?? []);
        $departmentIds = $this->ids($access['department_ids'] ?? []);
        $hasExplicitRules = ! empty($userIds) || ! empty($roleIds) || ! empty($departmentIds);

        if ($hasExplicitRules) {
            if (in_array((int) $user->id, $userIds, true)) {
                return true;
            }

            $context = $this->subjectContext($business->id, $user);
            if (! empty(array_intersect($context['role_ids'], $roleIds))) {
                return true;
            }

            $departmentId = $context['department_id'];
            return $departmentId && in_array($departmentId, $departmentIds, true);
        }

        return $user->canForBusiness('smart_documents.type.'.$type->code, (int) $business->id);
    }

    public function isBusinessAdmin(User $user, int $businessId): bool
    {
        return $user->hasRole('Admin#'.$businessId) || $user->can('superadmin');
    }

    private function activeDepartmentId(int $businessId, User $user): ?int
    {
        if (Schema::hasTable('hrm_employment_profiles')) {
            $profile = DB::table('hrm_employment_profiles')
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->value('department_id');
            if ($profile) {
                return (int) $profile;
            }
        }

        return (int) $user->business_id === $businessId && $user->essentials_department_id
            ? (int) $user->essentials_department_id
            : null;
    }

    private function subjectContext(int $businessId, User $user): array
    {
        $key = $businessId.':'.$user->id;
        if (! isset($this->subjectContextCache[$key])) {
            $this->subjectContextCache[$key] = [
                'role_ids' => $user->roles()->pluck(config('permission.table_names.roles').'.id')
                    ->map(fn ($id) => (int) $id)->all(),
                'department_id' => $this->activeDepartmentId($businessId, $user),
            ];
        }

        return $this->subjectContextCache[$key];
    }

    private function ids(array $values): array
    {
        return collect($values)->map(fn ($value) => (int) $value)->filter()->unique()->values()->all();
    }
}
