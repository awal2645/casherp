<?php

namespace Modules\Essentials\Http\Controllers;

use App\BusinessLocation;
use App\Category;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\HrmAuditEvent;
use Modules\Essentials\Entities\SensitiveAccessLog;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\EmploymentProfileService;
use Modules\Essentials\Services\HrmAuditService;

class EmploymentProfileController extends Controller
{
    use AuthorizesHrmRequests;

    protected ModuleUtil $moduleUtil;

    private EmploymentProfileService $profiles;

    private HrmAuditService $audit;

    public function __construct(ModuleUtil $moduleUtil, EmploymentProfileService $profiles, HrmAuditService $audit)
    {
        $this->moduleUtil = $moduleUtil;
        $this->profiles = $profiles;
        $this->audit = $audit;
    }

    public function index(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, ['essentials.view_employee_profiles', 'essentials.manage_employee_profiles']);
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = EmploymentProfile::forBusiness($businessId)
            ->with(['user:id,surname,first_name,last_name,email,contact_number', 'manager.user:id,surname,first_name,last_name']);
        if (! empty($validated['status'])) {
            $query->where('employment_status', $validated['status']);
        }
        if (! empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($query) use ($search) {
                $query->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $profiles = $query->orderBy('employee_number')->paginate(30)->withQueryString();
        $departments = Category::forDropdown($businessId, 'hrm_department');
        $summary = [
            'active' => EmploymentProfile::forBusiness($businessId)->whereIn('employment_status', ['active', 'probation', 'confirmed'])->count(),
            'probation' => EmploymentProfile::forBusiness($businessId)->where('employment_status', 'probation')->count(),
            'on_leave' => EmploymentProfile::forBusiness($businessId)->where('employment_status', 'on_leave')->count(),
            'ending_soon' => EmploymentProfile::forBusiness($businessId)
                ->whereNotNull('termination_date')
                ->whereBetween('termination_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count(),
        ];

        return view('essentials::employment.index', compact('profiles', 'departments', 'summary'));
    }

    public function show(Request $request, int $profile)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $record = EmploymentProfile::forBusiness($businessId)
            ->with(['user', 'manager.user', 'assignments' => fn ($query) => $query->latest('effective_from')])
            ->findOrFail($profile);
        $isSelf = (int) $record->user_id === (int) auth()->id();
        if (! $isSelf) {
            $this->authorizeHrmAction($businessId, ['essentials.view_employee_profiles', 'essentials.manage_employee_profiles']);
        } else {
            $this->authorizeHrmAction($businessId, ['essentials.employee_self_service', 'essentials.view_employee_profiles']);
        }

        $sensitive = $this->authorizedSensitivePayload($record, $isSelf, 'Employment profile viewed');
        $events = auth()->user()->canForBusiness('essentials.view_hr_audit', $businessId)
            ? HrmAuditEvent::where('business_id', $businessId)
                ->where('subject_type', EmploymentProfile::class)
                ->where('subject_id', $record->id)
                ->latest('occurred_at')
                ->limit(100)
                ->get()
            : collect();

        return view('essentials::employment.show', compact('record', 'sensitive', 'events', 'isSelf'));
    }

    public function edit(Request $request, int $profile)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_profiles');
        $record = EmploymentProfile::forBusiness($businessId)->with('user')->findOrFail($profile);

        return view('essentials::employment.edit', $this->formData($businessId, $record));
    }

    public function update(Request $request, int $profile)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_profiles');
        $record = EmploymentProfile::forBusiness($businessId)->findOrFail($profile);
        $data = $this->validateProfile($request, $businessId, $record);

        $this->validateManagerChain($record, $data['manager_profile_id'] ?? null, $businessId);
        $this->authorizeSensitiveChanges($request, $data, $businessId);
        $updated = $this->profiles->syncFromUserForm($businessId, $record->user, $data, (int) auth()->id());

        return redirect()->route('hrm.employment.show', $updated->id)
            ->with('status', ['success' => 1, 'msg' => __('lang_v1.updated_success')]);
    }

    public function selfService(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.employee_self_service');
        $profile = $this->profiles->find($businessId, (int) auth()->id())
            ?: $this->profiles->ensure($businessId, auth()->user(), (int) auth()->id());

        return redirect()->route('hrm.employment.show', $profile->id);
    }

    public function selfUpdate(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.employee_self_service');
        $profile = $this->profiles->findOrFail($businessId, (int) auth()->id());
        $data = $request->validate([
            'preferred_name' => ['nullable', 'string', 'max:191'],
            'work_phone' => ['nullable', 'string', 'max:60'],
            'emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:60'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:80'],
        ]);
        $personalData = (array) $profile->personal_data;
        $personalData['emergency_contact'] = Arr::only($data, [
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        ]);
        $data['personal_data'] = $personalData;
        $data['change_reason'] = 'Employee self-service profile update';
        $this->profiles->syncFromUserForm($businessId, $profile->user, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => __('lang_v1.updated_success')]);
    }

    public function audit(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $this->authorizeHrmAction($businessId, 'essentials.view_hr_audit');
        $events = HrmAuditEvent::where('business_id', $businessId)->latest('occurred_at')->paginate(50, ['*'], 'events');
        $accessLogs = SensitiveAccessLog::where('business_id', $businessId)->latest('accessed_at')->paginate(50, ['*'], 'access');

        return view('essentials::employment.audit', compact('events', 'accessLogs'));
    }

    private function validateProfile(Request $request, int $businessId, EmploymentProfile $record): array
    {
        return $request->validate([
            'employee_number' => ['required', 'string', 'max:80', Rule::unique('hrm_employment_profiles')->where('business_id', $businessId)->ignore($record->id)],
            'employment_status' => ['required', Rule::in(['active', 'inactive', 'probation', 'confirmed', 'suspended', 'on_leave', 'terminated'])],
            'employment_type' => ['nullable', Rule::in(['permanent', 'fixed_term', 'temporary', 'part_time', 'casual', 'contractor', 'intern', 'apprentice'])],
            'preferred_name' => ['nullable', 'string', 'max:191'],
            'work_email' => ['nullable', 'email', 'max:191'],
            'work_phone' => ['nullable', 'string', 'max:60'],
            'location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'department_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'designation_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('business_id', $businessId)->where('category_type', 'hrm_designation'))],
            'manager_profile_id' => ['nullable', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'job_title' => ['nullable', 'string', 'max:191'],
            'grade' => ['nullable', 'string', 'max:80'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'project_code' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'effective_from' => ['required', 'date'],
            'change_reason' => ['required', 'string', 'max:191'],
            'compensation.amount' => ['nullable', 'numeric', 'min:0'],
            'compensation.basis' => ['nullable', Rule::in(['hour', 'day', 'week', 'month', 'year'])],
            'compensation.pay_period' => ['nullable', Rule::in(['week', 'month'])],
            'compensation.pay_cycle' => ['nullable', 'string', 'max:50'],
            'compensation.currency_code' => ['nullable', 'string', 'size:3'],
            'bank_details' => ['nullable', 'array'],
            'bank_details.*' => ['nullable', 'string', 'max:191'],
            'tax_identifiers' => ['nullable', 'array'],
            'tax_identifiers.*' => ['nullable', 'string', 'max:191'],
            'medical_data' => ['nullable', 'array'],
            'medical_data.*' => ['nullable', 'string', 'max:2000'],
            'diversity_data' => ['nullable', 'array'],
            'diversity_data.*' => ['nullable', 'string', 'max:500'],
            'disciplinary_data' => ['nullable', 'array'],
            'disciplinary_data.*' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function formData(int $businessId, EmploymentProfile $record): array
    {
        $canBank = auth()->user()->canForBusiness('essentials.view_employee_bank', $businessId);
        $canTax = auth()->user()->canForBusiness('essentials.view_employee_tax', $businessId);
        $canRestricted = auth()->user()->canForBusiness('essentials.view_restricted_hr_data', $businessId);
        $sensitive = $this->authorizedSensitivePayload($record, false, 'Employment profile edit form opened');

        return [
            'record' => $record,
            'locations' => BusinessLocation::forDropdown($businessId, false, false, true, false),
            'departments' => Category::forDropdown($businessId, 'hrm_department'),
            'designations' => Category::forDropdown($businessId, 'hrm_designation'),
            'managers' => EmploymentProfile::forBusiness($businessId)
                ->where('id', '!=', $record->id)
                ->with('user:id,surname,first_name,last_name')
                ->get()
                ->pluck('user.user_full_name', 'id'),
            'sensitive' => $sensitive,
            'canBank' => $canBank,
            'canTax' => $canTax,
            'canRestricted' => $canRestricted,
        ];
    }

    private function authorizedSensitivePayload(EmploymentProfile $record, bool $isSelf, string $purpose): array
    {
        $payload = [
            'compensation' => [],
            'bank_details' => $record->maskedBankDetails(),
            'tax_identifiers' => [],
            'medical_data' => [],
            'diversity_data' => [],
            'disciplinary_data' => [],
            'personal_data' => [],
        ];
        $permissions = [
            'compensation' => 'essentials.view_employee_compensation',
            'bank_details' => 'essentials.view_employee_bank',
            'tax_identifiers' => 'essentials.view_employee_tax',
            'medical_data' => 'essentials.view_restricted_hr_data',
            'diversity_data' => 'essentials.view_restricted_hr_data',
            'disciplinary_data' => 'essentials.view_restricted_hr_data',
            'personal_data' => 'essentials.view_restricted_hr_data',
        ];
        foreach ($permissions as $field => $permission) {
            if (($isSelf && in_array($field, ['compensation', 'bank_details', 'tax_identifiers'], true)) || auth()->user()->canForBusiness($permission, (int) $record->business_id)) {
                $payload[$field] = (array) $record->{$field};
                $this->audit->recordSensitiveAccess($record, $field, $purpose);
            }
        }

        return $payload;
    }

    private function authorizeSensitiveChanges(Request $request, array $data, int $businessId): void
    {
        $map = [
            'compensation' => 'essentials.edit_employee_compensation',
            'bank_details' => 'essentials.edit_employee_bank',
            'tax_identifiers' => 'essentials.edit_employee_tax',
            'medical_data' => 'essentials.edit_restricted_hr_data',
            'diversity_data' => 'essentials.edit_restricted_hr_data',
            'disciplinary_data' => 'essentials.edit_restricted_hr_data',
            'personal_data' => 'essentials.edit_restricted_hr_data',
        ];
        foreach ($map as $field => $permission) {
            if ($request->has($field) && ! auth()->user()->canForBusiness($permission, $businessId)) {
                throw ValidationException::withMessages([$field => 'You are not allowed to edit this protected field group.']);
            }
        }
    }

    private function validateManagerChain(EmploymentProfile $record, $managerId, int $businessId): void
    {
        if (empty($managerId)) {
            return;
        }
        if ((int) $managerId === (int) $record->id) {
            throw ValidationException::withMessages(['manager_profile_id' => 'An employee cannot be their own manager.']);
        }
        $cursor = EmploymentProfile::forBusiness($businessId)->find($managerId);
        $visited = [];
        while ($cursor && $cursor->manager_profile_id) {
            if ((int) $cursor->manager_profile_id === (int) $record->id) {
                throw ValidationException::withMessages(['manager_profile_id' => 'This manager assignment would create a reporting cycle.']);
            }
            if (isset($visited[$cursor->id])) {
                break;
            }
            $visited[$cursor->id] = true;
            $cursor = EmploymentProfile::forBusiness($businessId)->find($cursor->manager_profile_id);
        }
    }
}
