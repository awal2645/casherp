@component('components.widget', ['title' => __('essentials::lang.hrm_details')])
<div class="row">
	<div class="col-md-6">
		<div class="form-group">
              {!! Form::label('essentials_department_id', __('essentials::lang.department') . ':') !!}
              <div class="form-group">
                  {!! Form::select('essentials_department_id', $departments, !empty($user->essentials_department_id) ? $user->essentials_department_id : null, ['class' => 'form-control select2', 'style' => 'width: 100%;', 'placeholder' => __('messages.please_select') ]); !!}
              </div>
          </div>
	</div>
	<div class="col-md-6">
		<div class="form-group">
            {!! Form::label('essentials_designation_id', __('essentials::lang.designation') . ':') !!}
            <div class="form-group">
                {!! Form::select('essentials_designation_id', $designations, !empty($user->essentials_designation_id) ? $user->essentials_designation_id : null, ['class' => 'form-control select2', 'style' => 'width: 100%;', 'placeholder' => __('messages.please_select') ]); !!}
            </div>
        </div>
	</div>
</div>
@can('essentials.manage_employee_profiles')
<div class="row">
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('employee_number', 'Employee number:') !!}
            {!! Form::text('employee_number', optional($employment_profile)->employee_number, ['class' => 'form-control', 'maxlength' => 80, 'placeholder' => 'Generated automatically when blank']) !!}
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('employment_status', 'Employment status:') !!}
            {!! Form::select('employment_status', ['active'=>'Active','probation'=>'Probation','confirmed'=>'Confirmed','suspended'=>'Suspended','on_leave'=>'On leave','terminated'=>'Terminated'], optional($employment_profile)->employment_status ?: 'active', ['class' => 'form-control select2']) !!}
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('employment_type', 'Employment type:') !!}
            {!! Form::select('employment_type', ['permanent'=>'Permanent','fixed_term'=>'Fixed term','temporary'=>'Temporary','part_time'=>'Part time','casual'=>'Casual','contractor'=>'Contractor','intern'=>'Intern','apprentice'=>'Apprentice'], optional($employment_profile)->employment_type, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            {!! Form::label('manager_profile_id', 'Line manager:') !!}
            {!! Form::select('manager_profile_id', $managers, optional($employment_profile)->manager_profile_id, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"><div class="form-group">{!! Form::label('job_title', 'Job title:') !!}{!! Form::text('job_title', optional($employment_profile)->job_title, ['class'=>'form-control','maxlength'=>191]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('grade', 'Grade:') !!}{!! Form::text('grade', optional($employment_profile)->grade, ['class'=>'form-control','maxlength'=>80]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('cost_center', 'Cost center:') !!}{!! Form::text('cost_center', optional($employment_profile)->cost_center, ['class'=>'form-control','maxlength'=>100]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('project_code', 'Project allocation:') !!}{!! Form::text('project_code', optional($employment_profile)->project_code, ['class'=>'form-control','maxlength'=>100]) !!}</div></div>
</div>
<div class="row">
    <div class="col-md-3"><div class="form-group">{!! Form::label('hire_date', 'Hire date:') !!}{!! Form::date('hire_date', optional(optional($employment_profile)->hire_date)->toDateString(), ['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('probation_end_date', 'Probation end:') !!}{!! Form::date('probation_end_date', optional(optional($employment_profile)->probation_end_date)->toDateString(), ['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('confirmation_date', 'Confirmation date:') !!}{!! Form::date('confirmation_date', optional(optional($employment_profile)->confirmation_date)->toDateString(), ['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('termination_date', 'Termination date:') !!}{!! Form::date('termination_date', optional(optional($employment_profile)->termination_date)->toDateString(), ['class'=>'form-control']) !!}</div></div>
</div>
<div class="row">
    <div class="col-md-3"><div class="form-group">{!! Form::label('effective_from', 'Change effective from:') !!}{!! Form::date('effective_from', now()->toDateString(), ['class'=>'form-control']) !!}</div></div>
    <div class="col-md-9"><div class="form-group">{!! Form::label('change_reason', 'Reason for employment change:') !!}{!! Form::text('change_reason', null, ['class'=>'form-control','maxlength'=>191,'placeholder'=>'Required context for the employment history and audit trail']) !!}</div></div>
</div>
@endcan
@endcomponent
@component('components.widget', ['title' => __('essentials::lang.payroll')])
<div class="row">
    <div class="col-md-4">
        {!! Form::label('location_id', __('lang_v1.primary_work_location') . ':') !!}
        {!! Form::select('location_id', $locations, !empty($user->location_id) ? $user->location_id : null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
    </div>
    <div class="col-md-4">
        @can('essentials.edit_employee_compensation')
        <div class="form-group">
            <div class="multi-input">
                {!! Form::label('essentials_salary', __('essentials::lang.salary') . ':') !!}
                <br/>
                {!! Form::number('essentials_salary', !empty($user->essentials_salary) ? $user->essentials_salary : null, ['class' => 'form-control width-40 pull-left', 'placeholder' => __('essentials::lang.salary')]); !!}

                {!! Form::select('essentials_pay_period', ['month' => __('essentials::lang.per'). ' '.__('lang_v1.month'), 'week' => __('essentials::lang.per'). ' '.__('essentials::lang.week'), 'day' => __('essentials::lang.per'). ' '.__('lang_v1.day')], !empty($user->essentials_pay_period) ? $user->essentials_pay_period : null, ['class' => 'form-control width-60 pull-left']); !!}
            </div>
        </div>
        @else
            @can('essentials.view_employee_compensation')
                <div class="form-group">
                    <label>@lang('essentials::lang.salary'):</label>
                    <p class="form-control-static">@if(!empty($user->essentials_salary)) @format_currency($user->essentials_salary) @endif</p>
                </div>
            @endcan
        @endcan
    </div>
    <div class="form-group col-md-4">
        {!! Form::label('pay_components', __('essentials::lang.pay_components') . ':') !!}
        {!! Form::select('pay_components[]', $pay_comoponenets, !empty($allowance_deduction_ids) ? $allowance_deduction_ids : [], ['class' => 'form-control select2', 'multiple' ]); !!}
    </div>
</div>
@endcomponent
