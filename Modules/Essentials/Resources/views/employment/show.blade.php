@extends('layouts.app')
@section('title', 'Employment Profile')
@section('content')
@include('essentials::layouts.nav_hrm')
<section class="content-header"><h1>{{$record->user->user_full_name}} <small>{{$record->employee_number}}</small></h1></section>
<section class="content">
    <div class="row">
        <div class="col-md-8">
            @component('components.widget', ['class'=>'box-primary','title'=>'Employment'])
                <div class="row">
                    <div class="col-md-4"><strong>Status</strong><p>{{ucwords(str_replace('_',' ',$record->employment_status))}}</p></div>
                    <div class="col-md-4"><strong>Employment type</strong><p>{{ucwords(str_replace('_',' ',$record->employment_type ?: '-'))}}</p></div>
                    <div class="col-md-4"><strong>Job / grade</strong><p>{{$record->job_title ?: '-'}} @if($record->grade)/ {{$record->grade}}@endif</p></div>
                    <div class="col-md-4"><strong>Manager</strong><p>{{$record->manager->user->user_full_name ?? '-'}}</p></div>
                    <div class="col-md-4"><strong>Cost center</strong><p>{{$record->cost_center ?: '-'}}</p></div>
                    <div class="col-md-4"><strong>Project allocation</strong><p>{{$record->project_code ?: '-'}}</p></div>
                    <div class="col-md-4"><strong>Hire date</strong><p>{{optional($record->hire_date)->format(session('business.date_format','Y-m-d')) ?: '-'}}</p></div>
                    <div class="col-md-4"><strong>Probation end</strong><p>{{optional($record->probation_end_date)->format(session('business.date_format','Y-m-d')) ?: '-'}}</p></div>
                    <div class="col-md-4"><strong>Termination date</strong><p>{{optional($record->termination_date)->format(session('business.date_format','Y-m-d')) ?: '-'}}</p></div>
                </div>
                @can('essentials.manage_employee_profiles')<a href="{{route('hrm.employment.edit',$record->id)}}" class="btn btn-primary"><i class="fa fa-edit"></i> Edit employment profile</a>@endcan
            @endcomponent

            @if($isSelf)
                @component('components.widget', ['title'=>'Employee self-service'])
                    {!! Form::open(['route'=>'hrm.employment.self.update','method'=>'PUT']) !!}
                    <div class="row">
                        <div class="col-md-4"><div class="form-group">{!! Form::label('preferred_name','Preferred name:') !!}{!! Form::text('preferred_name',$record->preferred_name,['class'=>'form-control','maxlength'=>191]) !!}</div></div>
                        <div class="col-md-4"><div class="form-group">{!! Form::label('work_phone','Work phone:') !!}{!! Form::text('work_phone',$record->work_phone,['class'=>'form-control','maxlength'=>60]) !!}</div></div>
                        <div class="col-md-4"><div class="form-group">{!! Form::label('emergency_contact_name','Emergency contact:') !!}{!! Form::text('emergency_contact_name',data_get($record->personal_data,'emergency_contact.emergency_contact_name'),['class'=>'form-control','maxlength'=>191]) !!}</div></div>
                        <div class="col-md-4"><div class="form-group">{!! Form::label('emergency_contact_phone','Emergency phone:') !!}{!! Form::text('emergency_contact_phone',data_get($record->personal_data,'emergency_contact.emergency_contact_phone'),['class'=>'form-control','maxlength'=>60]) !!}</div></div>
                        <div class="col-md-4"><div class="form-group">{!! Form::label('emergency_contact_relationship','Relationship:') !!}{!! Form::text('emergency_contact_relationship',data_get($record->personal_data,'emergency_contact.emergency_contact_relationship'),['class'=>'form-control','maxlength'=>80]) !!}</div></div>
                    </div>
                    <button class="btn btn-primary">Save my details</button>
                    {!! Form::close() !!}
                @endcomponent
            @endif

            @component('components.widget', ['title'=>'Assignment history'])
                <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Effective</th><th>Job</th><th>Grade</th><th>Cost center</th><th>Reason</th></tr></thead><tbody>
                    @forelse($record->assignments as $assignment)<tr><td>{{$assignment->effective_from->toDateString()}} – {{optional($assignment->effective_to)->toDateString() ?: 'Current'}}</td><td>{{$assignment->job_title ?: '-'}}</td><td>{{$assignment->grade ?: '-'}}</td><td>{{$assignment->cost_center ?: '-'}}</td><td>{{$assignment->change_reason}}</td></tr>@empty<tr><td colspan="5" class="text-muted">No assignment history.</td></tr>@endforelse
                </tbody></table></div>
            @endcomponent
        </div>
        <div class="col-md-4">
            @if(!empty($sensitive['compensation']))
                @component('components.widget', ['title'=>'Compensation'])
                    <p><strong>Amount:</strong> @format_currency(data_get($sensitive,'compensation.amount',0))</p>
                    <p><strong>Basis:</strong> {{ucwords(data_get($sensitive,'compensation.basis','-'))}}</p>
                    <p><strong>Pay period:</strong> {{ucwords(data_get($sensitive,'compensation.pay_period','-'))}}</p>
                @endcomponent
            @endif
            @if(!empty($sensitive['bank_details']))
                @component('components.widget', ['title'=>'Bank details'])
                    @foreach($sensitive['bank_details'] as $key=>$value)<p><strong>{{ucwords(str_replace('_',' ',$key))}}:</strong> {{$value}}</p>@endforeach
                @endcomponent
            @endif
            @if($events->isNotEmpty())
                @component('components.widget', ['title'=>'Audit timeline'])
                    @foreach($events as $event)<p><strong>{{$event->event_type}}</strong><br><small>{{$event->occurred_at}} · {{$event->reason}}</small></p>@endforeach
                @endcomponent
            @endif
        </div>
    </div>
</section>
@endsection
