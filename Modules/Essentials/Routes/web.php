<?php

// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'business.feature:hrm')->group(function () {
    Route::prefix('essentials')->group(function () {
        Route::get('/dashboard', [Modules\Essentials\Http\Controllers\DashboardController::class, 'essentialsDashboard']);
        Route::get('/install', [Modules\Essentials\Http\Controllers\InstallController::class, 'index']);
        Route::post('/install', [Modules\Essentials\Http\Controllers\InstallController::class, 'install']);
        Route::get('/install/update', [Modules\Essentials\Http\Controllers\InstallController::class, 'update']);
        Route::get('/install/uninstall', [Modules\Essentials\Http\Controllers\InstallController::class, 'uninstall']);

        Route::get('/', [Modules\Essentials\Http\Controllers\EssentialsController::class, 'index']);

        //document controller
        Route::resource('document', 'Modules\Essentials\Http\Controllers\DocumentController')->only(['index', 'store', 'destroy', 'show']);
        Route::get('document/download/{id}', [Modules\Essentials\Http\Controllers\DocumentController::class, 'download']);

        //document share controller
        Route::resource('document-share', 'Modules\Essentials\Http\Controllers\DocumentShareController')->only(['edit', 'update']);

        //todo controller
        Route::resource('todo', 'ToDoController');

        Route::post('todo/add-comment', [Modules\Essentials\Http\Controllers\ToDoController::class, 'addComment']);
        Route::get('todo/delete-comment/{id}', [Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteComment']);
        Route::get('todo/delete-document/{id}', [Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteDocument']);
        Route::post('todo/upload-document', [Modules\Essentials\Http\Controllers\ToDoController::class, 'uploadDocument']);
        Route::get('view-todo-{id}-share-docs', [Modules\Essentials\Http\Controllers\ToDoController::class, 'viewSharedDocs']);

        //reminder controller
        Route::resource('reminder', 'Modules\Essentials\Http\Controllers\ReminderController')->only(['index', 'store', 'edit', 'update', 'destroy', 'show']);

        //message controller
        Route::get('get-new-messages', [Modules\Essentials\Http\Controllers\EssentialsMessageController::class, 'getNewMessages']);
        Route::resource('messages', 'Modules\Essentials\Http\Controllers\EssentialsMessageController')->only(['index', 'store', 'destroy']);

        //Allowance and deduction controller
        Route::resource('allowance-deduction', 'Modules\Essentials\Http\Controllers\EssentialsAllowanceAndDeductionController')
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::resource('knowledge-base', 'Modules\Essentials\Http\Controllers\KnowledgeBaseController');

        Route::get('user-sales-targets', [Modules\Essentials\Http\Controllers\DashboardController::class, 'getUserSalesTargets']);
    });

    Route::prefix('hrm')->group(function () {
        Route::get('/dashboard', [Modules\Essentials\Http\Controllers\DashboardController::class, 'hrmDashboard'])->name('hrmDashboard');
        Route::get('/people', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'index'])->name('hrm.employment.index');
        Route::get('/people/self-service', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'selfService'])->name('hrm.employment.self');
        Route::put('/people/self-service', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'selfUpdate'])->name('hrm.employment.self.update');
        Route::get('/people/{profile}', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'show'])->whereNumber('profile')->name('hrm.employment.show');
        Route::get('/people/{profile}/edit', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'edit'])->whereNumber('profile')->name('hrm.employment.edit');
        Route::put('/people/{profile}', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'update'])->whereNumber('profile')->name('hrm.employment.update');
        Route::get('/audit', [Modules\Essentials\Http\Controllers\EmploymentProfileController::class, 'audit'])->name('hrm.audit.index');
        Route::get('/payroll-runs', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'index'])->name('hrm.payroll-runs.index');
        Route::post('/payroll-periods', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'storePeriod'])->name('hrm.payroll-periods.store');
        Route::post('/payroll-country-packs', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'storeCountryPack'])->name('hrm.payroll-country-packs.store');
        Route::post('/payroll-runs', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'storeRun'])->name('hrm.payroll-runs.store');
        Route::get('/payroll-runs/{run}', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'show'])->whereNumber('run')->name('hrm.payroll-runs.show');
        Route::post('/payroll-runs/{run}/calculate', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'calculate'])->whereNumber('run')->name('hrm.payroll-runs.calculate');
        Route::post('/payroll-runs/{run}/inputs', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'storeInput'])->whereNumber('run')->name('hrm.payroll-runs.inputs.store');
        Route::post('/payroll-inputs/{input}/decision', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'decideInput'])->whereNumber('input')->name('hrm.payroll-inputs.decision');
        Route::post('/payroll-runs/{run}/transition', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'transition'])->whereNumber('run')->name('hrm.payroll-runs.transition');
        Route::post('/payroll-runs/{run}/payment-batches', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'storePaymentBatch'])->whereNumber('run')->name('hrm.payroll-runs.payment-batches.store');
        Route::post('/payroll-payment-batches/{batch}/reconcile', [Modules\Essentials\Http\Controllers\PayrollGovernanceController::class, 'reconcilePaymentBatch'])->whereNumber('batch')->name('hrm.payroll-payment-batches.reconcile');

        Route::get('/workforce', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'index'])->name('hrm.workforce.index');
        Route::post('/workforce/calendars', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'storeCalendar'])->name('hrm.workforce.calendars.store');
        Route::post('/workforce/leave-accounts', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'storeLeaveAccount'])->name('hrm.workforce.leave-accounts.store');
        Route::post('/workforce/leave-accounts/{account}/ledger', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'postLeaveLedger'])->whereNumber('account')->name('hrm.workforce.leave-ledger.store');
        Route::post('/workforce/time-corrections', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'storeTimeCorrection'])->name('hrm.workforce.time-corrections.store');
        Route::post('/workforce/time-corrections/{correction}/decision', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'decideTimeCorrection'])->whereNumber('correction')->name('hrm.workforce.time-corrections.decision');
        Route::post('/workforce/documents', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'storeDocument'])->name('hrm.workforce.documents.store');
        Route::get('/workforce/documents/{document}/download', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'downloadDocument'])->whereNumber('document')->name('hrm.workforce.documents.download');
        Route::post('/workforce/lifecycle-events', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'storeLifecycleEvent'])->name('hrm.workforce.lifecycle.store');
        Route::post('/workforce/lifecycle-events/{event}/complete', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'completeLifecycleEvent'])->whereNumber('event')->name('hrm.workforce.lifecycle.complete');
        Route::post('/workforce/checklist-tasks/{task}/complete', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'completeChecklistTask'])->whereNumber('task')->name('hrm.workforce.checklist-tasks.complete');
        Route::post('/workforce/surveys/{survey}/responses', [Modules\Essentials\Http\Controllers\WorkforceController::class, 'submitSurveyResponse'])->whereNumber('survey')->name('hrm.workforce.surveys.respond');

        Route::get('/talent', [Modules\Essentials\Http\Controllers\TalentController::class, 'index'])->name('hrm.talent.index');
        Route::post('/talent/requisitions', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeRequisition'])->name('hrm.talent.requisitions.store');
        Route::post('/talent/requisitions/{requisition}/transition', [Modules\Essentials\Http\Controllers\TalentController::class, 'transitionRequisition'])->whereNumber('requisition')->name('hrm.talent.requisitions.transition');
        Route::post('/talent/candidates', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeCandidate'])->name('hrm.talent.candidates.store');
        Route::post('/talent/applications/{application}/advance', [Modules\Essentials\Http\Controllers\TalentController::class, 'advanceApplication'])->whereNumber('application')->name('hrm.talent.applications.advance');
        Route::post('/talent/interviews', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeInterview'])->name('hrm.talent.interviews.store');
        Route::post('/talent/performance-cycles', [Modules\Essentials\Http\Controllers\TalentController::class, 'storePerformanceCycle'])->name('hrm.talent.performance-cycles.store');
        Route::post('/talent/goals', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeGoal'])->name('hrm.talent.goals.store');
        Route::post('/talent/performance-reviews', [Modules\Essentials\Http\Controllers\TalentController::class, 'storePerformanceReview'])->name('hrm.talent.performance-reviews.store');
        Route::post('/talent/courses', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeCourse'])->name('hrm.talent.courses.store');
        Route::post('/talent/enrollments', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeEnrollment'])->name('hrm.talent.enrollments.store');
        Route::post('/talent/enrollments/{enrollment}/complete', [Modules\Essentials\Http\Controllers\TalentController::class, 'completeEnrollment'])->whereNumber('enrollment')->name('hrm.talent.enrollments.complete');
        Route::post('/talent/benefit-plans', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeBenefitPlan'])->name('hrm.talent.benefit-plans.store');
        Route::post('/talent/benefit-enrollments', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeBenefitEnrollment'])->name('hrm.talent.benefit-enrollments.store');
        Route::post('/talent/cases', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeCase'])->name('hrm.talent.cases.store');
        Route::post('/talent/cases/{case}/resolve', [Modules\Essentials\Http\Controllers\TalentController::class, 'resolveCase'])->whereNumber('case')->name('hrm.talent.cases.resolve');
        Route::post('/talent/succession', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeSuccessionPlan'])->name('hrm.talent.succession.store');
        Route::post('/talent/surveys', [Modules\Essentials\Http\Controllers\TalentController::class, 'storeSurvey'])->name('hrm.talent.surveys.store');
        Route::post('/talent/surveys/{survey}/transition', [Modules\Essentials\Http\Controllers\TalentController::class, 'transitionSurvey'])->whereNumber('survey')->name('hrm.talent.surveys.transition');

        Route::get('/analytics', [Modules\Essentials\Http\Controllers\WorkforceAnalyticsController::class, 'index'])->name('hrm.analytics.index');
        Route::get('/analytics/export', [Modules\Essentials\Http\Controllers\WorkforceAnalyticsController::class, 'export'])->name('hrm.analytics.export');
        Route::resource('/leave-type', 'Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController')
            ->only(['index', 'store', 'edit', 'update']);
        Route::resource('/leave', 'Modules\Essentials\Http\Controllers\EssentialsLeaveController')
            ->only(['index', 'create', 'store', 'destroy']);
        Route::post('/change-status', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'changeStatus']);
        Route::get('/leave/activity/{id}', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'activity']);
        Route::get('/user-leave-summary', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'getUserLeaveSummary']);
        Route::get('/change-leave-status', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'changeLeaveStatus']);

        Route::get('/settings', [Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'edit']);
        Route::post('/settings', [Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'update']);

        Route::post('/import-attendance', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'importAttendance']);
        Route::resource('/attendance', 'Modules\Essentials\Http\Controllers\AttendanceController')
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('/clock-in-clock-out', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'clockInClockOut']);

        Route::post('/validate-clock-in-clock-out', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'validateClockInClockOut']);

        Route::get('/get-attendance-by-shift', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceByShift']);
        Route::get('/get-attendance-by-date', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceByDate']);
        Route::get('/get-attendance-row/{user_id}', [Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceRow']);

        Route::get(
            '/user-attendance-summary',
            [Modules\Essentials\Http\Controllers\AttendanceController::class, 'getUserAttendanceSummary']
        );

        Route::get('/location-employees', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getEmployeesBasedOnLocation']);
        Route::get('/my-payrolls', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getMyPayrolls']);
        Route::get('/get-allowance-deduction-row', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getAllowanceAndDeductionRow']);
        Route::get('/payroll-group-datatable', [Modules\Essentials\Http\Controllers\PayrollController::class, 'payrollGroupDatatable']);
        Route::get('/view/{id}/payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'viewPayrollGroup']);
        Route::get('/edit/{id}/payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getEditPayrollGroup']);
        Route::post('/update-payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getUpdatePayrollGroup']);
        Route::get('/payroll-group/{id}/add-payment', [Modules\Essentials\Http\Controllers\PayrollController::class, 'addPayment']);
        Route::post('/post-payment-payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'postAddPayment']);
        Route::resource('/payroll', 'Modules\Essentials\Http\Controllers\PayrollController');
        Route::resource('/holiday', 'EssentialsHolidayController')
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        Route::get('/shift/assign-users/{shift_id}', [Modules\Essentials\Http\Controllers\ShiftController::class, 'getAssignUsers']);
        Route::post('/shift/assign-users', [Modules\Essentials\Http\Controllers\ShiftController::class, 'postAssignUsers']);
        Route::resource('/shift', 'Modules\Essentials\Http\Controllers\ShiftController')
            ->only(['index', 'create', 'store', 'edit', 'update']);
        Route::get('/sales-target', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'index']);
        Route::get('/set-sales-target/{id}', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'setSalesTarget']);
        Route::post('/save-sales-target', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'saveSalesTarget']);
    });
});
