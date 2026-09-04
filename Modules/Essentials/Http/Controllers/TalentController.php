<?php

namespace Modules\Essentials\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Category;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Essentials\Entities\EmploymentProfile;
use Modules\Essentials\Http\Controllers\Concerns\AuthorizesHrmRequests;
use Modules\Essentials\Services\HrmAuditService;

class TalentController extends Controller
{
    use AuthorizesHrmRequests;

    protected ModuleUtil $moduleUtil;
    private HrmAuditService $audit;

    public function __construct(ModuleUtil $moduleUtil, HrmAuditService $audit)
    {
        $this->moduleUtil = $moduleUtil;
        $this->audit = $audit;
    }

    public function index(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, $this->talentPermissions());
        $profiles = EmploymentProfile::forBusiness($businessId)->with('user:id,surname,first_name,last_name')->whereIn('employment_status', ['active', 'probation', 'confirmed', 'on_leave'])->orderBy('employee_number')->get();
        $requisitions = DB::table('hrm_job_requisitions')->where('business_id', $businessId)->whereNull('deleted_at')->latest()->limit(50)->get();
        $applications = DB::table('hrm_job_applications as a')->join('hrm_candidates as c', 'c.id', '=', 'a.candidate_id')->join('hrm_job_requisitions as r', 'r.id', '=', 'a.job_requisition_id')->where('a.business_id', $businessId)->whereNull('c.deleted_at')->whereNull('r.deleted_at')->select('a.*', 'c.first_name', 'c.last_name', 'c.email', 'r.title as job_title')->latest('a.applied_at')->limit(100)->get();
        $interviews = DB::table('hrm_interviews')->where('business_id', $businessId)->latest('starts_at')->limit(50)->get();
        $cycles = DB::table('hrm_performance_cycles')->where('business_id', $businessId)->latest('starts_on')->get();
        $goals = DB::table('hrm_performance_goals as g')->join('hrm_employment_profiles as p', 'p.id', '=', 'g.employment_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')->where('g.business_id', $businessId)->select('g.*', 'u.first_name', 'u.last_name')->latest('g.created_at')->limit(100)->get();
        $reviews = DB::table('hrm_performance_reviews as r')->join('hrm_employment_profiles as p', 'p.id', '=', 'r.employment_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')->where('r.business_id', $businessId)->select('r.*', 'u.first_name', 'u.last_name')->latest('r.submitted_at')->limit(100)->get();
        $courses = DB::table('hrm_learning_courses')->where('business_id', $businessId)->orderBy('title')->get();
        $enrollments = DB::table('hrm_learning_enrollments as e')->join('hrm_learning_courses as c', 'c.id', '=', 'e.learning_course_id')->join('hrm_employment_profiles as p', 'p.id', '=', 'e.employment_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')->where('e.business_id', $businessId)->select('e.*', 'c.title as course_title', 'u.first_name', 'u.last_name')->latest('e.assigned_on')->limit(100)->get();
        $benefitPlans = DB::table('hrm_benefit_plans')->where('business_id', $businessId)->orderBy('name')->get();
        $benefitEnrollments = DB::table('hrm_benefit_enrollments as e')->join('hrm_benefit_plans as b', 'b.id', '=', 'e.benefit_plan_id')->join('hrm_employment_profiles as p', 'p.id', '=', 'e.employment_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')->where('e.business_id', $businessId)->select('e.*', 'b.name as plan_name', 'u.first_name', 'u.last_name')->latest('e.starts_on')->limit(100)->get();
        $cases = DB::table('hrm_employee_cases')->where('business_id', $businessId)->whereNull('deleted_at')->latest('reported_on')->limit(50)->get();
        $succession = DB::table('hrm_succession_plans')->where('business_id', $businessId)->latest()->limit(50)->get();
        $surveys = DB::table('hrm_engagement_surveys')->where('business_id', $businessId)->latest('opens_on')->limit(50)->get();
        $departments = Category::forDropdown($businessId, 'hrm_department');
        $locations = BusinessLocation::forDropdown($businessId, false, false, true, false);
        $business = Business::with('currency')->findOrFail($businessId);
        $currency = optional($business->currency)->code ?: 'USD';

        return view('essentials::talent.index', compact('profiles', 'requisitions', 'applications', 'interviews', 'cycles', 'goals', 'reviews', 'courses', 'enrollments', 'benefitPlans', 'benefitEnrollments', 'cases', 'succession', 'surveys', 'departments', 'locations', 'currency'));
    }

    public function storeRequisition(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_recruitment');
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:80', Rule::unique('hrm_job_requisitions')->where('business_id', $businessId)], 'title' => ['required', 'string', 'max:191'],
            'department_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))],
            'location_id' => ['nullable', Rule::exists('business_locations', 'id')->where('business_id', $businessId)],
            'hiring_manager_profile_id' => ['nullable', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'headcount' => ['required', 'integer', 'min:1', 'max:10000'], 'employment_type' => ['nullable', 'string', 'max:40'],
            'currency_code' => ['nullable', 'string', 'size:3'], 'salary_min' => ['nullable', 'numeric', 'min:0'], 'salary_max' => ['nullable', 'numeric', 'gte:salary_min'],
            'description' => ['nullable', 'string', 'max:10000'], 'requirements' => ['nullable', 'string', 'max:10000'], 'target_start_date' => ['nullable', 'date'],
        ]);
        $id = DB::table('hrm_job_requisitions')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'draft', 'requested_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'recruitment.requisition_created', 'hrm_job_requisition', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Job requisition created.']);
    }

    public function transitionRequisition(Request $request, int $requisition)
    {
        $businessId = $this->businessId($request);
        $data = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'open', 'close', 'cancel'])]]);
        $this->authorizeHrmAction($businessId, $data['action'] === 'approve' ? 'essentials.approve_recruitment' : 'essentials.manage_recruitment');
        DB::transaction(function () use ($businessId, $requisition, $data) {
            $record = DB::table('hrm_job_requisitions')->where('business_id', $businessId)->whereNull('deleted_at')->lockForUpdate()->find($requisition);
            abort_unless($record, 404);
            $transitions = ['draft' => ['submit' => 'pending_approval', 'cancel' => 'cancelled'], 'pending_approval' => ['approve' => 'approved', 'cancel' => 'cancelled'], 'approved' => ['open' => 'open', 'cancel' => 'cancelled'], 'open' => ['close' => 'closed', 'cancel' => 'cancelled']];
            $next = $transitions[$record->status][$data['action']] ?? null;
            if (! $next) throw ValidationException::withMessages(['action' => 'This requisition transition is not allowed.']);
            if ($data['action'] === 'approve' && (int) $record->requested_by === (int) auth()->id()) throw ValidationException::withMessages(['action' => 'A different authorized user must approve this requisition.']);
            $updates = ['status' => $next, 'updated_at' => now()];
            if ($data['action'] === 'approve') $updates += ['approved_by' => auth()->id(), 'approved_at' => now()];
            if ($data['action'] === 'close') $updates['closed_on'] = now()->toDateString();
            DB::table('hrm_job_requisitions')->where('id', $record->id)->update($updates);
            $this->audit->record($businessId, 'recruitment.requisition_'.$data['action'], 'hrm_job_requisition', $record->id, (array) $record, $updates);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Requisition status updated.']);
    }

    public function storeCandidate(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_recruitment');
        $data = $request->validate([
            'job_requisition_id' => ['required', Rule::exists('hrm_job_requisitions', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('status', 'open')->whereNull('deleted_at'))],
            'first_name' => ['required', 'string', 'max:191'], 'last_name' => ['required', 'string', 'max:191'], 'email' => ['nullable', 'email', 'max:191'], 'phone' => ['nullable', 'string', 'max:80'],
            'country_code' => ['nullable', 'string', 'size:2'], 'source' => ['nullable', 'string', 'max:80'], 'retention_until' => ['nullable', 'date', 'after:today'], 'consent' => ['accepted'],
        ]);
        DB::transaction(function () use ($businessId, $data) {
            $candidateId = DB::table('hrm_candidates')->insertGetId([
                'uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null, 'country_code' => ! empty($data['country_code']) ? strtoupper($data['country_code']) : null, 'source' => $data['source'] ?? null,
                'consents' => json_encode(['recruitment_processing' => true, 'captured_at' => now()->toIso8601String()]), 'retention_until' => $data['retention_until'] ?? now()->addYear()->toDateString(),
                'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $applicationId = DB::table('hrm_job_applications')->insertGetId(['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'job_requisition_id' => $data['job_requisition_id'], 'candidate_id' => $candidateId, 'stage' => 'applied', 'status' => 'active', 'owner_user_id' => auth()->id(), 'applied_at' => now(), 'last_stage_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record($businessId, 'recruitment.application_created', 'hrm_job_application', $applicationId, [], ['candidate_id' => $candidateId, 'job_requisition_id' => $data['job_requisition_id']]);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Candidate application added with consent and retention controls.']);
    }

    public function advanceApplication(Request $request, int $application)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_recruitment');
        $data = $request->validate(['stage' => ['required', Rule::in(['screening', 'shortlisted', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'])], 'rating' => ['nullable', 'numeric', 'between:0,5'], 'decision_reason' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($businessId, $application, $data) {
            $record = DB::table('hrm_job_applications')->where('business_id', $businessId)->lockForUpdate()->find($application);
            abort_unless($record, 404);
            if ($record->status !== 'active') throw ValidationException::withMessages(['stage' => 'This application is already closed.']);
            $order = ['applied' => 0, 'screening' => 1, 'shortlisted' => 2, 'interview' => 3, 'offer' => 4, 'hired' => 5];
            if (isset($order[$data['stage']]) && isset($order[$record->stage]) && $order[$data['stage']] !== $order[$record->stage] + 1) throw ValidationException::withMessages(['stage' => 'Move the application through one pipeline stage at a time.']);
            if (in_array($data['stage'], ['rejected', 'withdrawn'], true) && empty($data['decision_reason'])) throw ValidationException::withMessages(['decision_reason' => 'A decision reason is required.']);
            $updates = ['stage' => $data['stage'], 'status' => in_array($data['stage'], ['hired', 'rejected', 'withdrawn'], true) ? 'closed' : 'active', 'rating' => $data['rating'] ?? $record->rating, 'decision_reason' => $data['decision_reason'] ?? null, 'last_stage_at' => now(), 'updated_at' => now()];
            DB::table('hrm_job_applications')->where('id', $record->id)->update($updates);
            $this->audit->record($businessId, 'recruitment.application_stage_changed', 'hrm_job_application', $record->id, (array) $record, $updates, $data['decision_reason'] ?? null);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Application stage updated.']);
    }

    public function storeInterview(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_recruitment');
        $data = $request->validate([
            'job_application_id' => ['required', Rule::exists('hrm_job_applications', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('status', 'active'))],
            'interview_type' => ['required', Rule::in(['phone', 'video', 'in_person', 'panel', 'technical'])], 'starts_at' => ['required', 'date', 'after:now'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'timezone'], 'location_or_link' => ['nullable', 'string', 'max:500'], 'interviewer_user_ids' => ['required', 'array', 'min:1'], 'interviewer_user_ids.*' => ['integer'],
        ]);
        $validInterviewers = User::forBusiness($businessId)->whereIn('id', $data['interviewer_user_ids'])->count();
        if ($validInterviewers !== count(array_unique($data['interviewer_user_ids']))) throw ValidationException::withMessages(['interviewer_user_ids' => 'Every interviewer must belong to the active company.']);
        $data['interviewer_user_ids'] = json_encode(array_values(array_unique($data['interviewer_user_ids'])));
        $id = DB::transaction(function () use ($businessId, $data) {
            $application = DB::table('hrm_job_applications')->where('business_id', $businessId)->where('status', 'active')->lockForUpdate()->find($data['job_application_id']);
            abort_unless($application, 404);
            if (! in_array($application->stage, ['shortlisted', 'interview'], true)) throw ValidationException::withMessages(['job_application_id' => 'Shortlist the candidate before scheduling an interview.']);
            $id = DB::table('hrm_interviews')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'scheduled', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('hrm_job_applications')->where('business_id', $businessId)->where('id', $application->id)->update(['stage' => 'interview', 'last_stage_at' => now(), 'updated_at' => now()]);

            return $id;
        });
        $this->audit->record($businessId, 'recruitment.interview_scheduled', 'hrm_interview', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Interview scheduled.']);
    }

    public function storePerformanceCycle(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_performance');
        $data = $request->validate(['name' => ['required', 'string', 'max:191'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'], 'review_due_on' => ['nullable', 'date', 'after_or_equal:ends_on']]);
        $id = DB::table('hrm_performance_cycles')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'active', 'rating_scale' => json_encode(['min' => 1, 'max' => 5, 'labels' => ['Needs improvement', 'Developing', 'Effective', 'Strong', 'Exceptional']]), 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'performance.cycle_created', 'hrm_performance_cycle', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Performance cycle created.']);
    }

    public function storeGoal(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_performance');
        $data = $request->validate([
            'performance_cycle_id' => ['required', Rule::exists('hrm_performance_cycles', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('status', 'active'))], 'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'title' => ['required', 'string', 'max:191'], 'description' => ['nullable', 'string', 'max:5000'], 'weight' => ['required', 'numeric', 'gt:0', 'lte:100'],
            'measure_type' => ['required', Rule::in(['percentage', 'number', 'currency', 'milestone'])], 'target_value' => ['nullable', 'numeric'], 'due_on' => ['nullable', 'date'],
        ]);
        $id = DB::transaction(function () use ($businessId, $data) {
            EmploymentProfile::forBusiness($businessId)->lockForUpdate()->findOrFail($data['employment_profile_id']);
            $weight = DB::table('hrm_performance_goals')->where('business_id', $businessId)->where('performance_cycle_id', $data['performance_cycle_id'])->where('employment_profile_id', $data['employment_profile_id'])->where('status', 'active')->sum('weight');
            if ((float) $weight + (float) $data['weight'] > 100.0) throw ValidationException::withMessages(['weight' => 'Active goal weights for an employee cannot exceed 100%.']);

            return DB::table('hrm_performance_goals')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'current_value' => 0, 'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->audit->record($businessId, 'performance.goal_created', 'hrm_performance_goal', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Performance goal added.']);
    }

    public function storePerformanceReview(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_performance');
        $data = $request->validate([
            'performance_cycle_id' => ['required', Rule::exists('hrm_performance_cycles', 'id')->where('business_id', $businessId)],
            'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)],
            'review_type' => ['required', Rule::in(['manager', 'peer', 'self', 'calibration'])], 'overall_rating' => ['required', 'numeric', 'between:1,5'],
            'strengths' => ['required', 'string', 'max:10000'], 'development_areas' => ['required', 'string', 'max:10000'],
        ]);
        $reviewer = EmploymentProfile::forBusiness($businessId)->where('user_id', auth()->id())->first();
        if (! $reviewer) throw ValidationException::withMessages(['reviewer' => 'The reviewer needs an employment profile in the active company.']);
        if ($data['review_type'] === 'self' && (int) $data['employment_profile_id'] !== (int) $reviewer->id) throw ValidationException::withMessages(['review_type' => 'A self review can only be submitted for your own employment profile.']);
        if ($data['review_type'] === 'peer' && (int) $data['employment_profile_id'] === (int) $reviewer->id) throw ValidationException::withMessages(['review_type' => 'Use self review when reviewing your own profile.']);
        $duplicate = DB::table('hrm_performance_reviews')->where('performance_cycle_id', $data['performance_cycle_id'])->where('employment_profile_id', $data['employment_profile_id'])->where('reviewer_profile_id', $reviewer->id)->where('review_type', $data['review_type'])->exists();
        if ($duplicate) throw ValidationException::withMessages(['review_type' => 'This reviewer has already submitted that review type for the employee and cycle.']);
        $id = DB::table('hrm_performance_reviews')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'reviewer_profile_id' => $reviewer->id, 'status' => 'submitted', 'ratings' => json_encode(['overall' => (float) $data['overall_rating']]), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'performance.review_submitted', 'hrm_performance_review', $id, [], ['cycle_id' => $data['performance_cycle_id'], 'employment_profile_id' => $data['employment_profile_id'], 'review_type' => $data['review_type'], 'overall_rating' => $data['overall_rating']]);

        return back()->with('status', ['success' => 1, 'msg' => 'Performance review submitted.']);
    }

    public function storeCourse(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_learning');
        $data = $request->validate(['code' => ['required', 'string', 'max:80', Rule::unique('hrm_learning_courses')->where('business_id', $businessId)], 'title' => ['required', 'string', 'max:191'], 'description' => ['nullable', 'string', 'max:5000'], 'delivery_method' => ['required', Rule::in(['classroom', 'virtual', 'self_paced', 'on_the_job'])], 'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'is_mandatory' => ['nullable', 'boolean'], 'validity_months' => ['nullable', 'integer', 'min:1', 'max:1200']]);
        $id = DB::table('hrm_learning_courses')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'is_mandatory' => $request->boolean('is_mandatory'), 'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'learning.course_created', 'hrm_learning_course', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Learning course created.']);
    }

    public function storeEnrollment(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_learning');
        $data = $request->validate(['learning_course_id' => ['required', Rule::exists('hrm_learning_courses', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('status', 'active'))], 'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)], 'assigned_on' => ['required', 'date'], 'due_on' => ['nullable', 'date', 'after_or_equal:assigned_on']]);
        $exists = DB::table('hrm_learning_enrollments')->where($data)->exists();
        if ($exists) throw ValidationException::withMessages(['employment_profile_id' => 'This employee is already assigned to the course on the selected date.']);
        $id = DB::table('hrm_learning_enrollments')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'assigned', 'assigned_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'learning.enrollment_created', 'hrm_learning_enrollment', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Employee enrolled.']);
    }

    public function completeEnrollment(Request $request, int $enrollment)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_learning');
        $data = $request->validate(['score' => ['nullable', 'numeric', 'between:0,100'], 'certificate_number' => ['nullable', 'string', 'max:191'], 'certificate_expires_on' => ['nullable', 'date', 'after:today']]);
        DB::transaction(function () use ($businessId, $enrollment, $data) {
            $record = DB::table('hrm_learning_enrollments')->where('business_id', $businessId)->lockForUpdate()->find($enrollment);
            abort_unless($record, 404);
            if (! in_array($record->status, ['assigned', 'in_progress'], true)) throw ValidationException::withMessages(['enrollment' => 'This enrollment can no longer be completed.']);
            $updates = $data + ['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()];
            DB::table('hrm_learning_enrollments')->where('id', $record->id)->update($updates);
            $this->audit->record($businessId, 'learning.enrollment_completed', 'hrm_learning_enrollment', $record->id, (array) $record, $updates);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Learning completion recorded.']);
    }

    public function storeBenefitPlan(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_benefits');
        $data = $request->validate(['code' => ['required', 'string', 'max:80', Rule::unique('hrm_benefit_plans')->where('business_id', $businessId)], 'name' => ['required', 'string', 'max:191'], 'benefit_type' => ['required', 'string', 'max:60'], 'provider' => ['nullable', 'string', 'max:191'], 'currency_code' => ['nullable', 'string', 'size:3'], 'employer_amount' => ['required', 'numeric', 'min:0'], 'employee_amount' => ['required', 'numeric', 'min:0'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $id = DB::table('hrm_benefit_plans')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'eligibility_rules' => json_encode([]), 'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'benefits.plan_created', 'hrm_benefit_plan', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Benefit plan created.']);
    }

    public function storeBenefitEnrollment(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_benefits');
        $data = $request->validate(['benefit_plan_id' => ['required', Rule::exists('hrm_benefit_plans', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('status', 'active'))], 'employment_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)], 'starts_on' => ['required', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]);
        $newEnd = $data['ends_on'] ?? '9999-12-31';
        $id = DB::transaction(function () use ($businessId, $data, $newEnd) {
            EmploymentProfile::forBusiness($businessId)->lockForUpdate()->findOrFail($data['employment_profile_id']);
            $overlap = DB::table('hrm_benefit_enrollments')->where('business_id', $businessId)->where('benefit_plan_id', $data['benefit_plan_id'])->where('employment_profile_id', $data['employment_profile_id'])->where('status', 'active')
                ->whereDate('starts_on', '<=', $newEnd)
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $data['starts_on']))
                ->exists();
            if ($overlap) throw ValidationException::withMessages(['employment_profile_id' => 'The employee already has an overlapping active enrollment.']);

            return DB::table('hrm_benefit_enrollments')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'active', 'approved_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->audit->record($businessId, 'benefits.employee_enrolled', 'hrm_benefit_enrollment', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Benefit enrollment created.']);
    }

    public function storeCase(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_relations');
        $data = $request->validate(['employment_profile_id' => ['nullable', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)], 'case_number' => ['required', 'string', 'max:80', Rule::unique('hrm_employee_cases')->where('business_id', $businessId)], 'case_type' => ['required', Rule::in(['grievance', 'disciplinary', 'safety', 'accommodation', 'ethics', 'other'])], 'severity' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])], 'title' => ['required', 'string', 'max:191'], 'description' => ['required', 'string', 'max:10000'], 'reported_on' => ['required', 'date'], 'target_resolution_on' => ['nullable', 'date', 'after_or_equal:reported_on']]);
        $data['description'] = Crypt::encryptString($data['description']);
        $id = DB::table('hrm_employee_cases')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'open', 'access_level' => 'restricted', 'owner_user_id' => auth()->id(), 'reported_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'employee_relations.case_created', 'hrm_employee_case', $id, [], ['case_number' => $data['case_number'], 'case_type' => $data['case_type'], 'severity' => $data['severity'], 'status' => 'open'], 'Restricted case created');

        return back()->with('status', ['success' => 1, 'msg' => 'Restricted employee case created.']);
    }

    public function resolveCase(Request $request, int $case)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_employee_relations');
        $data = $request->validate(['outcome' => ['required', 'string', 'max:5000'], 'actions' => ['nullable', 'string', 'max:5000']]);
        $record = DB::table('hrm_employee_cases')->where('business_id', $businessId)->whereNull('deleted_at')->find($case);
        abort_unless($record, 404);
        if ($record->status === 'resolved') throw ValidationException::withMessages(['outcome' => 'This case is already resolved.']);
        $updates = ['status' => 'resolved', 'resolved_on' => now()->toDateString(), 'resolution' => json_encode(['encrypted' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR))]), 'updated_at' => now()];
        DB::table('hrm_employee_cases')->where('id', $record->id)->update($updates);
        $this->audit->record($businessId, 'employee_relations.case_resolved', 'hrm_employee_case', $record->id, (array) $record, ['status' => 'resolved'], 'Restricted outcome recorded');

        return back()->with('status', ['success' => 1, 'msg' => 'Case resolution recorded.']);
    }

    public function storeSuccessionPlan(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_succession');
        $data = $request->validate(['critical_role' => ['required', 'string', 'max:191'], 'department_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('business_id', $businessId)->where('category_type', 'hrm_department'))], 'incumbent_profile_id' => ['nullable', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)], 'successor_profile_id' => ['required', Rule::exists('hrm_employment_profiles', 'id')->where('business_id', $businessId)], 'readiness' => ['required', Rule::in(['ready_now', 'one_year', 'two_years', 'long_term'])], 'risk_score' => ['nullable', 'numeric', 'between:0,100'], 'development_plan' => ['nullable', 'string', 'max:10000']]);
        if (! empty($data['incumbent_profile_id']) && (int) $data['incumbent_profile_id'] === (int) $data['successor_profile_id']) throw ValidationException::withMessages(['successor_profile_id' => 'The incumbent cannot be their own successor.']);
        $id = DB::table('hrm_succession_plans')->insertGetId($data + ['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'status' => 'active', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'succession.plan_created', 'hrm_succession_plan', $id, [], $data);

        return back()->with('status', ['success' => 1, 'msg' => 'Succession plan created.']);
    }

    public function storeSurvey(Request $request)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_engagement');
        $data = $request->validate(['name' => ['required', 'string', 'max:191'], 'opens_on' => ['required', 'date'], 'closes_on' => ['required', 'date', 'after_or_equal:opens_on'], 'questions_json' => ['required', 'json', 'max:50000'], 'is_anonymous' => ['nullable', 'boolean'], 'minimum_group_size' => ['required', 'integer', 'min:3', 'max:1000']]);
        $questions = json_decode($data['questions_json'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($questions) || count($questions) < 1 || count($questions) > 50) throw ValidationException::withMessages(['questions_json' => 'Provide between 1 and 50 survey questions.']);
        $questionIds = [];
        foreach ($questions as $index => $question) {
            $id = (string) ($question['id'] ?? '');
            $text = trim((string) ($question['text'] ?? ''));
            $type = (string) ($question['type'] ?? 'text');
            if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || isset($questionIds[$id]) || $text === '' || mb_strlen($text) > 500 || ! in_array($type, ['scale', 'text'], true)) {
                throw ValidationException::withMessages(['questions_json' => "Survey question {$index} is invalid or has a duplicate identifier."]);
            }
            $questionIds[$id] = true;
            $questions[$index] = ['id' => $id, 'text' => $text, 'type' => $type];
        }
        $id = DB::table('hrm_engagement_surveys')->insertGetId(['uuid' => (string) Str::uuid(), 'business_id' => $businessId, 'name' => $data['name'], 'opens_on' => $data['opens_on'], 'closes_on' => $data['closes_on'], 'questions' => json_encode($questions), 'is_anonymous' => $request->boolean('is_anonymous', true), 'minimum_group_size' => $data['minimum_group_size'], 'status' => 'draft', 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($businessId, 'engagement.survey_created', 'hrm_engagement_survey', $id, [], ['name' => $data['name'], 'question_count' => count($questions)]);

        return back()->with('status', ['success' => 1, 'msg' => 'Engagement survey created.']);
    }

    public function transitionSurvey(Request $request, int $survey)
    {
        $businessId = $this->businessId($request);
        $this->authorizeHrmAction($businessId, 'essentials.manage_engagement');
        $data = $request->validate(['action' => ['required', Rule::in(['launch', 'close'])]]);
        $record = DB::table('hrm_engagement_surveys')->where('business_id', $businessId)->find($survey);
        abort_unless($record, 404);
        $allowed = $data['action'] === 'launch' ? $record->status === 'draft' : $record->status === 'open';
        if (! $allowed) throw ValidationException::withMessages(['action' => 'This survey transition is not allowed.']);
        $status = $data['action'] === 'launch' ? 'open' : 'closed';
        DB::table('hrm_engagement_surveys')->where('id', $record->id)->update(['status' => $status, 'updated_at' => now()]);
        $this->audit->record($businessId, 'engagement.survey_'.$data['action'], 'hrm_engagement_survey', $record->id, (array) $record, ['status' => $status]);

        return back()->with('status', ['success' => 1, 'msg' => 'Survey status updated.']);
    }

    private function talentPermissions(): array
    {
        return ['essentials.manage_recruitment', 'essentials.approve_recruitment', 'essentials.manage_performance', 'essentials.manage_learning', 'essentials.manage_benefits', 'essentials.manage_employee_relations', 'essentials.manage_succession', 'essentials.manage_engagement'];
    }

    private function businessId(Request $request): int { return (int) $request->session()->get('user.business_id'); }
}
