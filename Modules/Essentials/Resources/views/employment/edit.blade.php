@extends('layouts.app')
@section('title', 'Edit Employment Profile')
@section('content')
@include('essentials::layouts.nav_hrm')
<section class="content-header"><h1>Edit {{$record->user->user_full_name}} <small>{{$record->employee_number}}</small></h1></section>
<section class="content">
{!! Form::open(['route'=>['hrm.employment.update',$record->id],'method'=>'PUT']) !!}
@component('components.widget', ['class'=>'box-primary','title'=>'Employment and organization'])
<div class="row">
    <div class="col-md-3"><div class="form-group">{!! Form::label('employee_number','Employee number:') !!}{!! Form::text('employee_number',$record->employee_number,['class'=>'form-control','required','maxlength'=>80]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('employment_status','Status:') !!}{!! Form::select('employment_status',['active'=>'Active','inactive'=>'Inactive','probation'=>'Probation','confirmed'=>'Confirmed','suspended'=>'Suspended','on_leave'=>'On leave','terminated'=>'Terminated'],$record->employment_status,['class'=>'form-control select2','required']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('employment_type','Type:') !!}{!! Form::select('employment_type',['permanent'=>'Permanent','fixed_term'=>'Fixed term','temporary'=>'Temporary','part_time'=>'Part time','casual'=>'Casual','contractor'=>'Contractor','intern'=>'Intern','apprentice'=>'Apprentice'],$record->employment_type,['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('manager_profile_id','Line manager:') !!}{!! Form::select('manager_profile_id',$managers,$record->manager_profile_id,['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('location_id','Location:') !!}{!! Form::select('location_id',$locations,$record->location_id,['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('department_id','Department:') !!}{!! Form::select('department_id',$departments,$record->department_id,['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('designation_id','Designation:') !!}{!! Form::select('designation_id',$designations,$record->designation_id,['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('job_title','Job title:') !!}{!! Form::text('job_title',$record->job_title,['class'=>'form-control','maxlength'=>191]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('grade','Grade:') !!}{!! Form::text('grade',$record->grade,['class'=>'form-control','maxlength'=>80]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('cost_center','Cost center:') !!}{!! Form::text('cost_center',$record->cost_center,['class'=>'form-control','maxlength'=>100]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('project_code','Project allocation:') !!}{!! Form::text('project_code',$record->project_code,['class'=>'form-control','maxlength'=>100]) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('hire_date','Hire date:') !!}{!! Form::date('hire_date',optional($record->hire_date)->toDateString(),['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('probation_end_date','Probation end:') !!}{!! Form::date('probation_end_date',optional($record->probation_end_date)->toDateString(),['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('confirmation_date','Confirmation date:') !!}{!! Form::date('confirmation_date',optional($record->confirmation_date)->toDateString(),['class'=>'form-control']) !!}</div></div>
    <div class="col-md-3"><div class="form-group">{!! Form::label('termination_date','Termination date:') !!}{!! Form::date('termination_date',optional($record->termination_date)->toDateString(),['class'=>'form-control']) !!}</div></div>
</div>
@endcomponent
@can('essentials.edit_employee_compensation')
@component('components.widget', ['title'=>'Protected compensation'])
<div class="row"><div class="col-md-3"><div class="form-group">{!! Form::label('compensation[amount]','Salary / rate:') !!}{!! Form::number('compensation[amount]',data_get($sensitive,'compensation.amount'),['class'=>'form-control','min'=>0,'step'=>'0.0001']) !!}</div></div><div class="col-md-3"><div class="form-group">{!! Form::label('compensation[basis]','Basis:') !!}{!! Form::select('compensation[basis]',['hour'=>'Hour','day'=>'Day','week'=>'Week','month'=>'Month','year'=>'Year'],data_get($sensitive,'compensation.basis'),['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div><div class="col-md-3"><div class="form-group">{!! Form::label('compensation[pay_period]','Pay period:') !!}{!! Form::select('compensation[pay_period]',['week'=>'Weekly','month'=>'Monthly'],data_get($sensitive,'compensation.pay_period'),['class'=>'form-control select2','placeholder'=>__('messages.please_select')]) !!}</div></div><div class="col-md-3"><div class="form-group">{!! Form::label('compensation[currency_code]','Currency:') !!}{!! Form::text('compensation[currency_code]',data_get($sensitive,'compensation.currency_code'),['class'=>'form-control','maxlength'=>3]) !!}</div></div></div>
@endcomponent
@endcan
@if($canBank && auth()->user()->can('essentials.edit_employee_bank'))
@component('components.widget', ['title'=>'Protected bank details'])
<div class="row">@foreach(['account_holder_name'=>'Account holder','account_number'=>'Account number / IBAN','bank_name'=>'Bank name','bank_code'=>'Bank / routing code','branch'=>'Branch'] as $key=>$label)<div class="col-md-4"><div class="form-group">{!! Form::label("bank_details[$key]",$label.':') !!}{!! Form::text("bank_details[$key]",data_get($sensitive,"bank_details.$key"),['class'=>'form-control','maxlength'=>191,'autocomplete'=>'off']) !!}</div></div>@endforeach</div>
@endcomponent
@endif
@if($canTax && auth()->user()->can('essentials.edit_employee_tax'))
@component('components.widget', ['title'=>'Protected tax and statutory identifiers'])
<div class="row"><div class="col-md-4"><div class="form-group">{!! Form::label('tax_identifiers[tax_number]','Tax number:') !!}{!! Form::text('tax_identifiers[tax_number]',data_get($sensitive,'tax_identifiers.tax_number'),['class'=>'form-control','maxlength'=>191,'autocomplete'=>'off']) !!}</div></div><div class="col-md-4"><div class="form-group">{!! Form::label('tax_identifiers[social_insurance]','Social insurance:') !!}{!! Form::text('tax_identifiers[social_insurance]',data_get($sensitive,'tax_identifiers.social_insurance'),['class'=>'form-control','maxlength'=>191,'autocomplete'=>'off']) !!}</div></div><div class="col-md-4"><div class="form-group">{!! Form::label('tax_identifiers[pension_number]','Pension number:') !!}{!! Form::text('tax_identifiers[pension_number]',data_get($sensitive,'tax_identifiers.pension_number'),['class'=>'form-control','maxlength'=>191,'autocomplete'=>'off']) !!}</div></div></div>
@endcomponent
@endif
@component('components.widget', ['title'=>'Effective-dated change record'])
<div class="row"><div class="col-md-3"><div class="form-group">{!! Form::label('effective_from','Effective from:') !!}{!! Form::date('effective_from',now()->toDateString(),['class'=>'form-control','required']) !!}</div></div><div class="col-md-9"><div class="form-group">{!! Form::label('change_reason','Reason:') !!}{!! Form::text('change_reason',null,['class'=>'form-control','required','maxlength'=>191,'placeholder'=>'Explain why this employment record is changing']) !!}</div></div></div>
<button class="btn btn-primary"><i class="fa fa-save"></i> Save employment profile</button> <a class="btn btn-default" href="{{route('hrm.employment.show',$record->id)}}">Cancel</a>
@endcomponent
{!! Form::close() !!}
</section>
@endsection
