<section class="no-print">
    @php
        $hrmBusinessId = (int) session()->get('user.business_id');
        $hrmUser = auth()->user();
        $hrmIsAdministrator = $hrmUser->can('superadmin') || $hrmUser->hasRole('Admin#'.$hrmBusinessId);
        $hrmCan = function ($ability) use ($hrmUser, $hrmBusinessId, $hrmIsAdministrator) {
            return $hrmIsAdministrator || $hrmUser->canForBusiness($ability, $hrmBusinessId);
        };
    @endphp
    <nav class="navbar-default tw-transition-all tw-duration-5000 tw-shrink-0 tw-rounded-2xl tw-m-[16px] tw-border-2 !tw-bg-white">
        <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false" style="margin-top: 3px; margin-right: 3px;">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{action([\Modules\Essentials\Http\Controllers\DashboardController::class, 'hrmDashboard'])}}"><i class="fa fas fa-users"></i> {{__('essentials::lang.hrm')}}</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    @if($hrmCan('essentials.view_employee_profiles') || $hrmCan('essentials.manage_employee_profiles'))
                        <li @if(request()->segment(2) == 'people' && request()->segment(3) != 'self-service') class="active" @endif><a href="{{route('hrm.employment.index')}}">People</a></li>
                    @endif
                    @if($hrmCan('essentials.employee_self_service'))
                        <li @if(request()->segment(3) == 'self-service') class="active" @endif><a href="{{route('hrm.employment.self')}}">My HR</a></li>
                    @endif
                    @if($hrmCan('essentials.crud_leave_type'))
                        <li @if(request()->segment(2) == 'leave-type') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController::class, 'index'])}}">@lang('essentials::lang.leave_type')</a></li>
                    @endif
                    @if($hrmCan('essentials.crud_all_leave') || $hrmCan('essentials.crud_own_leave'))
                        <li @if(request()->segment(2) == 'leave') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'index'])}}">@lang('essentials::lang.leave')</a></li>
                    @endif
                    @if($hrmCan('essentials.crud_all_attendance') || $hrmCan('essentials.view_own_attendance'))
                    <li @if(request()->segment(2) == 'attendance') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\AttendanceController::class, 'index'])}}">@lang('essentials::lang.attendance')</a></li>
                    @endif
                    <li @if(request()->segment(2) == 'payroll') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\PayrollController::class, 'index'])}}">@lang('essentials::lang.payroll')</a></li>
                    @if($hrmCan('essentials.manage_payroll_runs') || $hrmCan('essentials.approve_payroll_runs'))
                        <li @if(request()->segment(2) == 'payroll-runs') class="active" @endif><a href="{{route('hrm.payroll-runs.index')}}">Payroll Control</a></li>
                    @endif
                    @if($hrmCan('essentials.manage_workforce') || $hrmCan('essentials.manage_hr_documents') || $hrmCan('essentials.employee_self_service'))
                        <li @if(request()->segment(2) == 'workforce') class="active" @endif><a href="{{route('hrm.workforce.index')}}">Workforce</a></li>
                    @endif
                    @if($hrmCan('essentials.manage_recruitment') || $hrmCan('essentials.approve_recruitment') || $hrmCan('essentials.manage_performance') || $hrmCan('essentials.manage_learning') || $hrmCan('essentials.manage_benefits') || $hrmCan('essentials.manage_employee_relations') || $hrmCan('essentials.manage_succession') || $hrmCan('essentials.manage_engagement'))
                        <li @if(request()->segment(2) == 'talent') class="active" @endif><a href="{{route('hrm.talent.index')}}">Talent</a></li>
                    @endif
                    @if($hrmCan('essentials.view_workforce_analytics'))
                        <li @if(request()->segment(2) == 'analytics') class="active" @endif><a href="{{route('hrm.analytics.index')}}">Analytics</a></li>
                    @endif

                    <li @if(request()->segment(2) == 'holiday') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\EssentialsHolidayController::class, 'index'])}}">@lang('essentials::lang.holiday')</a></li>
                    @if($hrmCan('essentials.crud_department'))
                    <li @if(request()->get('type') == 'hrm_department') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=hrm_department'}}">@lang('essentials::lang.departments')</a></li>
                    @endif
                    
                    @if($hrmCan('essentials.crud_designation'))
                    <li @if(request()->get('type') == 'hrm_designation') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=hrm_designation'}}">@lang('essentials::lang.designations')</a></li>
                    @endif

                    @if($hrmCan('essentials.access_sales_target'))
                        <li @if(request()->segment(1) == 'hrm' && request()->segment(2) == 'sales-target') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\SalesTargetController::class, 'index'])}}">@lang('essentials::lang.sales_target')</a></li>
                    @endif

                    @if($hrmCan('edit_essentials_settings'))
                        <li @if(request()->segment(1) == 'hrm' && request()->segment(2) == 'settings') class="active" @endif><a href="{{action([\Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'edit'])}}">@lang('business.settings')</a></li>
                    @endif
                    @if($hrmCan('essentials.view_hr_audit'))
                        <li @if(request()->segment(2) == 'audit') class="active" @endif><a href="{{route('hrm.audit.index')}}">Audit</a></li>
                    @endif
                </ul>

            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>
