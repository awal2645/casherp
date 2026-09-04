<?php

namespace Modules\Essentials\Http\Controllers;

use App\BusinessLocation;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EmployeeDocument;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Entities\HrmChecklist;
use Modules\Essentials\Entities\HrmChecklistTask;
use Modules\Essentials\Entities\LeaveAccount;
use Modules\Essentials\Entities\LifecycleEvent;
use Modules\Essentials\Entities\TimeCorrection;
use Modules\Essentials\Entities\WorkCalendar;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\HrmAuditService;
use Modules\Essentials\Services\LeaveLedgerService;

class WorkforceController extends Controller
{
    use AuthorizesHrmRequests;

    protected ModuleUtil $moduleUtil;
    private LeaveLedgerService $leaveLedger;
    private HrmAuditService $audit;

    public function __construct(ModuleUtil $moduleUtil, LeaveLedgerService $leaveLedger, HrmAuditService $audit)
    {
        $this->moduleUtil = $moduleUtil;
        $this->leaveLedger = $leaveLedger;
        $this->audit = $audit;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, ['essentials.manage_workforce', 'essentials.manage_hr_documents', 'essentials.employee_self_service']);
        $isManager = auth()->user()->canForBusiness('essentials.manage_workforce', $businessId) || $this->moduleUtil->is_admin(auth()->user(), $businessId) || auth()->user()->can('superadmin');
        $ownProfile = EmploymentProfile::forBusiness($businessId)->where('user_id', auth()->id())->first();
        $profiles = EmploymentProfile::forBusiness($businessId)->with('user:id,surname,first_name,last_name')->whereIn('employment_status', ['active', 'probation', 'confirmed', 'on_leave'])->orderBy('employee_number')->get();
        $corrections = TimeCorrection::forBusiness($businessId)->with('profile.user')
            ->when(! $isManager, fn ($query) => $query->where('employment_profile_id', optional($ownProfile)->id ?: 0))
            ->latest()->limit(50)->get();
        $leaveAccounts = LeaveAccount::forBusiness($businessId)->with(['profile.user', 'leaveType'])
            ->when(! $isManager, fn ($query) => $query->where('employment_profile_id', optional($ownProfile)->id ?: 0))
            ->latest()->limit(100)->get();
        $documents = EmployeeDocument::forBusiness($businessId)->with('profile.user')
            ->when(! $isManager, fn ($query) => $query->where('employment_profile_id', optional($ownProfile)->id ?: 0)->whereIn('access_level', ['employee', 'employee_hr']))
            ->latest()->limit(50)->get();
        $lifecycleEvents = LifecycleEvent::forBusiness($businessId)->with(['profile.user', 'checklists.tasks'])
            ->when(! $isManager, fn ($query) => $query->where('employment_profile_id', optional($ownProfile)->id ?: 0))
            ->latest('effective_date')->limit(50)->get();
        $calendars = WorkCalendar::forBusiness($businessId)->orderByDesc('is_default')->orderBy('name')->get();
        $activeSurveys = DB::table('hrm_engagement_surveys')->where('business_id', $businessId)->where('status', 'open')->whereDate('opens_on', '<=', now()->toDateString())->whereDate('closes_on', '>=', now()->toDateString())->orderBy('closes_on')->get();
        $leaveTypes = EssentialsLeaveType::forDropdown($businessId);
        $locations = BusinessLocation::forDropdown($businessId, false, false, true, false);
        $summary = [
            'pending_corrections' => TimeCorrection::forBusiness($businessId)->where('status', 'pending')->count(),
            'expiring_documents' => EmployeeDocument::forBusiness($businessId)->where('status', 'active')->whereBetween('expires_on', [now()->toDateString(), now()->addDays(60)->toDateString()])->count(),
            'open_checklists' => HrmChecklist::forBusiness($businessId)->where('status', 'open')->count(),
            'active_calendars' => WorkCalendar::forBusiness($businessId)->where('is_active', true)->count(),
        ];

        return view('essentials::workforce.index', compact('profiles', 'ownProfile', 'corrections', 'leaveAccounts', 'documents', 'lifecycleEvents', 'calendars', 'activeSurveys', 'leaveTypes', 'locations', 'summary', 'isManager'));
    }

    public function storeCalendar(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_workforce');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('hrm_work_calendars')->where('business_id', $businessId)],
            'location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'timezone' => ['required', 'timezone'],
            'standard_hours_per_week' => ['required', 'numeric', 'min:1', 'max:168'],
            'weekly_schedule_json' => ['required', 'json'],
            'weekend_days' => ['nullable', 'array'], 'weekend_days.*' => [Rule::in(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'])],
            'is_default' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $schedule = json_decode($data['weekly_schedule_json'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($schedule)) throw ValidationException::withMessages(['weekly_schedule_json' => 'The weekly schedule must be a JSON object.']);
        $calendar = DB::transaction(function () use ($businessId, $data, $schedule, $request) {
            if ($request->boolean('is_default')) WorkCalendar::forBusiness($businessId)->update(['is_default' => false]);

            return WorkCalendar::create([
                'business_id' => $businessId, 'location_id' => $data['location_id'] ?? null, 'name' => $data['name'], 'timezone' => $data['timezone'],
                'weekly_schedule' => $schedule, 'weekend_days' => $data['weekend_days'] ?? [], 'standard_hours_per_week' => $data['standard_hours_per_week'],
                'is_default' => $request->boolean('is_default'), 'is_active' => $request->boolean('is_active', true), 'created_by' => auth()->id(),
            ]);
        });
        $this->audit->record($businessId, 'workforce.calendar_created', WorkCalendar::class, $calendar->id, [], $calendar->toArray());

        return back()->with('status', ['success' => 1, 'msg' => 'Work calendar created.']);
    }

    public function storeLeaveAccount(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_workforce');
        $data = $request->validate([
            'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'leave_type_id' => ['required', Rule::exists('essentials_leave_types', 'id')->where('business_id', $businessId)],
            'policy_period' => ['required', 'string', 'max:40'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);
        $account = LeaveAccount::firstOrCreate(
            ['business_id' => $businessId, 'employment_profile_id' => $data['employment_profile_id'], 'leave_type_id' => $data['leave_type_id'], 'policy_period' => $data['policy_period']],
            ['opening_balance' => 0]
        );
        if ((float) $data['opening_balance'] > 0 && ! $account->ledgerEntries()->exists()) {
            $this->leaveLedger->post($account, 'opening', (float) $data['opening_balance'], now()->toDateString(), 'Opening leave balance', (int) auth()->id());
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Leave account ready.']);
    }

    public function postLeaveLedger(Request $request, int $account)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_workforce');
        $record = LeaveAccount::forBusiness($businessId)->findOrFail($account);
        $data = $request->validate([
            'entry_type' => ['required', Rule::in(['accrual', 'carry_forward', 'adjustment', 'reservation', 'release', 'usage', 'expiry'])],
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-1000,1000'], 'effective_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:191'],
        ]);
        $this->leaveLedger->post($record, $data['entry_type'], (float) $data['quantity'], $data['effective_date'], $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Leave ledger entry posted.']);
    }

    public function storeTimeCorrection(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, ['essentials.employee_self_service', 'essentials.manage_workforce']);
        $isManager = auth()->user()->canForBusiness('essentials.manage_workforce', $businessId) || $this->moduleUtil->is_admin(auth()->user(), $businessId) || auth()->user()->can('superadmin');
        $data = $request->validate([
            'employment_profile_id' => ['nullable', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'attendance_id' => ['nullable', Rule::exists('essentials_attendances', 'id')->where('business_id', $businessId)],
            'correction_type' => ['required', Rule::in(['missed_clock_in', 'missed_clock_out', 'time_change', 'break_change', 'overtime'])],
            'requested_clock_in' => ['nullable', 'date'], 'requested_clock_out' => ['nullable', 'date', 'after:requested_clock_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'], 'overtime_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $requiredField = [
            'missed_clock_in' => 'requested_clock_in',
            'missed_clock_out' => 'requested_clock_out',
            'break_change' => 'break_minutes',
            'overtime' => 'overtime_minutes',
        ][$data['correction_type']] ?? null;
        if ($requiredField && ! isset($data[$requiredField])) {
            throw ValidationException::withMessages([$requiredField => 'This value is required for the selected correction type.']);
        }
        if ($data['correction_type'] === 'time_change' && empty($data['requested_clock_in']) && empty($data['requested_clock_out'])) {
            throw ValidationException::withMessages(['requested_clock_in' => 'Enter at least one corrected clock time.']);
        }
        $own = EmploymentProfile::forBusiness($businessId)->where('user_id', auth()->id())->first();
        if (! $isManager && ! $own) throw ValidationException::withMessages(['employment_profile_id' => 'You need an employment profile in the active company before requesting a correction.']);
        if ($isManager && empty($data['employment_profile_id']) && ! $own) throw ValidationException::withMessages(['employment_profile_id' => 'Select an employee for this correction.']);
        $profileId = $isManager && ! empty($data['employment_profile_id']) ? (int) $data['employment_profile_id'] : $own->id;
        if (! empty($data['attendance_id'])) {
            $attendance = EssentialsAttendance::where('business_id', $businessId)->findOrFail($data['attendance_id']);
            $profile = EmploymentProfile::forBusiness($businessId)->findOrFail($profileId);
            if ((int) $attendance->user_id !== (int) $profile->user_id) throw ValidationException::withMessages(['attendance_id' => 'The attendance record does not belong to the selected employee.']);
        }
        $data['employment_profile_id'] = $profileId;
        $record = TimeCorrection::create($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'pending', 'requested_by' => auth()->id()]);
        $this->audit->record($businessId, 'time.correction_requested', TimeCorrection::class, $record->id, [], $record->toArray(), $data['reason']);

        return back()->with('status', ['success' => 1, 'msg' => 'Time correction submitted for approval.']);
    }

    public function decideTimeCorrection(Request $request, int $correction)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_workforce');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($businessId, $correction, $data) {
            $record = TimeCorrection::forBusiness($businessId)->with('profile')->lockForUpdate()->findOrFail($correction);
            if ($record->status !== 'pending') throw ValidationException::withMessages(['decision' => 'This correction has already been decided.']);
            if ((int) $record->requested_by === (int) auth()->id()) throw ValidationException::withMessages(['decision' => 'A different authorized user must decide this correction.']);
            if ($data['decision'] === 'approved' && $record->attendance_id) {
                $attendance = EssentialsAttendance::where('business_id', $businessId)->lockForUpdate()->findOrFail($record->attendance_id);
                if ((int) $attendance->user_id !== (int) $record->profile->user_id) throw ValidationException::withMessages(['attendance' => 'Employee context mismatch.']);
                if ($record->requested_clock_in) $attendance->clock_in_time = $record->requested_clock_in;
                if ($record->requested_clock_out) $attendance->clock_out_time = $record->requested_clock_out;
                $attendance->save();
            }
            $record->update(['status' => $data['decision'], 'decided_by' => auth()->id(), 'decision_note' => $data['decision_note'], 'decided_at' => now()]);
            $this->audit->record($businessId, 'time.correction_'.$data['decision'], TimeCorrection::class, $record->id, [], $record->fresh()->toArray(), $data['decision_note']);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Time correction decision recorded.']);
    }

    public function storeDocument(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_hr_documents');
        $data = $request->validate([
            'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'document_type' => ['required', 'string', 'max:80'], 'name' => ['required', 'string', 'max:191'], 'version' => ['nullable', 'string', 'max:40'],
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'access_level' => ['required', Rule::in(['employee', 'employee_hr', 'hr_only', 'restricted'])],
            'issued_on' => ['nullable', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'], 'retention_until' => ['nullable', 'date'],
        ]);
        if (! empty($data['expires_on']) && ! empty($data['retention_until']) && \Carbon\Carbon::parse($data['retention_until'])->lt(\Carbon\Carbon::parse($data['expires_on']))) {
            throw ValidationException::withMessages(['retention_until' => 'The retention date cannot be earlier than the document expiry date.']);
        }
        $file = $request->file('document');
        $path = Storage::disk('local')->putFile("hrm_documents/{$businessId}/{$data['employment_profile_id']}", $file);
        if (! $path) throw ValidationException::withMessages(['document' => 'The document could not be stored. Please try again.']);
        $record = EmployeeDocument::create([
            'uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'employment_profile_id' => $data['employment_profile_id'],
            'document_type' => $data['document_type'], 'name' => $data['name'], 'version' => $data['version'] ?? '1', 'file_path' => $path,
            'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'access_level' => $data['access_level'],
            'issued_on' => $data['issued_on'] ?? null, 'expires_on' => $data['expires_on'] ?? null, 'retention_until' => $data['retention_until'] ?? null,
            'legal_hold' => false, 'status' => 'active', 'uploaded_by' => auth()->id(),
        ]);
        $this->audit->record($businessId, 'document.uploaded', EmployeeDocument::class, $record->id, [], ['document_type' => $record->document_type, 'name' => $record->name, 'version' => $record->version, 'access_level' => $record->access_level, 'status' => $record->status]);

        return back()->with('status', ['success' => 1, 'msg' => 'Employee document stored securely.']);
    }

    public function downloadDocument(Request $request, int $document)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmFeature($businessId);
        $record = EmployeeDocument::forBusiness($businessId)->with('profile')->findOrFail($document);
        $isSelf = (int) $record->profile->user_id === (int) auth()->id();
        $maySelfAccess = $isSelf && in_array($record->access_level, ['employee', 'employee_hr'], true);
        abort_unless($maySelfAccess || auth()->user()->canForBusiness('essentials.manage_hr_documents', $businessId) || $this->moduleUtil->is_admin(auth()->user(), $businessId) || auth()->user()->can('superadmin'), 403);
        abort_unless(Storage::disk('local')->exists($record->file_path), 404);
        $this->audit->recordSensitiveAccess($record->profile, 'employee_document', 'Employee document downloaded', 'download');

        return Storage::disk('local')->download($record->file_path, $record->name.'.'.pathinfo($record->file_path, PATHINFO_EXTENSION));
    }

    public function storeLifecycleEvent(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_profiles');
        $data = $request->validate([
            'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'event_type' => ['required', Rule::in(['onboarding', 'probation', 'confirmation', 'transfer', 'promotion', 'return_to_work', 'offboarding'])],
            'effective_date' => ['required', 'date'], 'reason' => ['required', 'string', 'max:2000'], 'checklist_name' => ['nullable', 'string', 'max:191'],
        ]);
        $event = DB::transaction(function () use ($businessId, $data) {
            $event = LifecycleEvent::create($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'planned', 'details' => [], 'created_by' => auth()->id()]);
            $defaults = $this->defaultChecklistTasks($data['event_type']);
            if ($defaults) {
                $checklist = HrmChecklist::create(['business_id' => $businessId, 'employment_profile_id' => $data['employment_profile_id'], 'lifecycle_event_id' => $event->id, 'checklist_type' => $data['event_type'], 'name' => $data['checklist_name'] ?? ucwords(str_replace('_', ' ', $data['event_type'])).' checklist', 'due_date' => $data['effective_date'], 'status' => 'open', 'created_by' => auth()->id()]);
                foreach ($defaults as $sort => $title) HrmChecklistTask::create(['business_id' => $businessId, 'checklist_id' => $checklist->id, 'title' => $title, 'due_date' => $data['effective_date'], 'status' => 'pending', 'sort_order' => $sort]);
            }

            return $event;
        });
        $this->audit->record($businessId, 'lifecycle.event_created', LifecycleEvent::class, $event->id, [], $event->toArray(), $data['reason']);

        return back()->with('status', ['success' => 1, 'msg' => 'Lifecycle event and checklist created.']);
    }

    public function completeChecklistTask(Request $request, int $task)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_profiles');
        DB::transaction(function () use ($businessId, $task) {
            $record = HrmChecklistTask::where('business_id', $businessId)->lockForUpdate()->findOrFail($task);
            if ($record->status !== 'pending') throw ValidationException::withMessages(['task' => 'This checklist task is already complete.']);
            $record->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => auth()->id()]);
            $checklist = HrmChecklist::forBusiness($businessId)->lockForUpdate()->findOrFail($record->checklist_id);
            if (! $checklist->tasks()->where('status', '!=', 'completed')->exists()) $checklist->update(['status' => 'completed']);
            $this->audit->record($businessId, 'checklist.task_completed', HrmChecklistTask::class, $record->id, [], $record->toArray());
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Checklist task completed.']);
    }

    public function completeLifecycleEvent(Request $request, int $event)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_profiles');
        DB::transaction(function () use ($businessId, $event) {
            $record = LifecycleEvent::forBusiness($businessId)->with('checklists.tasks')->lockForUpdate()->findOrFail($event);
            if ($record->status !== 'planned') throw ValidationException::withMessages(['event' => 'This lifecycle event is no longer pending.']);
            if ($record->effective_date->isFuture()) throw ValidationException::withMessages(['event' => 'A lifecycle event cannot be completed before its effective date.']);
            if ($record->checklists->flatMap->tasks->contains(fn ($task) => $task->status !== 'completed')) {
                throw ValidationException::withMessages(['event' => 'Complete every checklist task before applying this lifecycle event.']);
            }

            $profile = EmploymentProfile::forBusiness($businessId)->lockForUpdate()->findOrFail($record->employment_profile_id);
            $profileChanges = [];
            if (in_array($record->event_type, ['onboarding', 'return_to_work'], true)) $profileChanges['employment_status'] = 'active';
            if ($record->event_type === 'probation') $profileChanges['employment_status'] = 'probation';
            if ($record->event_type === 'confirmation') $profileChanges += ['employment_status' => 'confirmed', 'confirmation_date' => $record->effective_date];
            if ($record->event_type === 'offboarding') $profileChanges += ['employment_status' => 'terminated', 'termination_date' => $record->effective_date];
            if ($profileChanges) $profile->update($profileChanges + ['version' => $profile->version + 1, 'updated_by' => auth()->id()]);

            $details = (array) $record->details;
            $details['completed_at'] = now()->toIso8601String();
            $record->update(['status' => 'completed', 'approved_by' => auth()->id(), 'details' => $details]);
            $this->audit->record($businessId, 'lifecycle.event_completed', LifecycleEvent::class, $record->id, ['status' => 'planned'], ['status' => 'completed', 'profile_changes' => $profileChanges], $record->reason);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Lifecycle event completed and the employment status was synchronized.']);
    }

    public function submitSurveyResponse(Request $request, int $survey)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.employee_self_service');
        $profile = EmploymentProfile::forBusiness($businessId)->where('user_id', auth()->id())->firstOrFail();
        $record = DB::table('hrm_engagement_surveys')->where('business_id', $businessId)->where('status', 'open')->whereDate('opens_on', '<=', now()->toDateString())->whereDate('closes_on', '>=', now()->toDateString())->find($survey);
        abort_unless($record, 404);
        $questions = json_decode($record->questions, true) ?: [];
        $rules = ['answers' => ['required', 'array']];
        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');
            if ($id !== '') $rules['answers.'.$id] = ['required', ($question['type'] ?? 'text') === 'scale' ? 'integer' : 'string', ($question['type'] ?? 'text') === 'scale' ? 'between:1,5' : 'max:2000'];
        }
        $data = $request->validate($rules);
        $questionIds = collect($questions)->pluck('id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        $data['answers'] = Arr::only($data['answers'], $questionIds);
        $tokenHash = hash_hmac('sha256', $businessId.'|'.$record->id.'|'.$profile->id, (string) config('app.key'));
        DB::transaction(function () use ($businessId, $record, $profile, $data, $tokenHash) {
            $duplicate = DB::table('hrm_engagement_responses')->where('business_id', $businessId)->where('engagement_survey_id', $record->id)
                ->where(fn ($query) => $record->is_anonymous ? $query->where('anonymous_token_hash', $tokenHash) : $query->where('employment_profile_id', $profile->id))
                ->lockForUpdate()->exists();
            if ($duplicate) throw ValidationException::withMessages(['answers' => 'You have already submitted this survey.']);
            DB::table('hrm_engagement_responses')->insert([
                'uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'engagement_survey_id' => $record->id,
                'employment_profile_id' => $record->is_anonymous ? null : $profile->id,
                'anonymous_token_hash' => $record->is_anonymous ? $tokenHash : null,
                'answers' => json_encode($data['answers']), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Survey response submitted.']);
    }

    private function defaultChecklistTasks(string $type): array
    {
        return match ($type) {
            'onboarding' => ['Verify employment documents', 'Issue policies and equipment', 'Assign orientation and mandatory learning', 'Confirm payroll and bank setup'],
            'offboarding' => ['Record final working date', 'Recover company assets', 'Revoke system and premises access', 'Prepare final payroll and statutory documents'],
            'return_to_work' => ['Confirm return date and fitness requirements', 'Restore access and work schedule'],
            default => ['Confirm approvals and supporting documents', 'Update the effective-dated employment assignment'],
        };
    }

    private function businessId(Request $request): int { return (int) $request->session()->get('user.business_id'); }
}
