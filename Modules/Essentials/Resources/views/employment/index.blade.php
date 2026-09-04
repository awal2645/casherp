@extends('layouts.app')
@section('title', 'Employee Profiles')
@section('content')
@include('essentials::layouts.nav_hrm')
<section class="content-header">
    <h1>Employee Profiles <small>Company-specific employment records</small></h1>
</section>
<section class="content">
    <div class="row">
        @foreach(['active'=>'Active employees','probation'=>'On probation','on_leave'=>'On leave','ending_soon'=>'Ending within 30 days'] as $key => $label)
            <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span><div class="info-box-content"><span class="info-box-text">{{$label}}</span><span class="info-box-number">{{$summary[$key]}}</span></div></div></div>
        @endforeach
    </div>
    @component('components.widget', ['class' => 'box-primary', 'title' => 'People'])
        {!! Form::open(['method'=>'GET','route'=>'hrm.employment.index','class'=>'row']) !!}
        <div class="col-md-4"><div class="form-group">{!! Form::label('search','Search:') !!}{!! Form::text('search',request('search'),['class'=>'form-control','placeholder'=>'Name, email, employee number or job title']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('status','Status:') !!}{!! Form::select('status',['active'=>'Active','inactive'=>'Inactive','probation'=>'Probation','confirmed'=>'Confirmed','suspended'=>'Suspended','on_leave'=>'On leave','terminated'=>'Terminated'],request('status'),['class'=>'form-control select2','placeholder'=>'All statuses']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('department_id','Department:') !!}{!! Form::select('department_id',$departments,request('department_id'),['class'=>'form-control select2','placeholder'=>'All departments']) !!}</div></div>
        <div class="col-md-2"><div class="form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block"><i class="fa fa-filter"></i> Apply</button></div></div>
        {!! Form::close() !!}
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Employee</th><th>Employee number</th><th>Job</th><th>Status</th><th>Manager</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                @forelse($profiles as $profile)
                    <tr>
                        <td><strong>{{$profile->user->user_full_name}}</strong><br><small>{{$profile->work_email ?: $profile->user->email}}</small></td>
                        <td>{{$profile->employee_number}}</td>
                        <td>{{$profile->job_title ?: '-'}} @if($profile->grade)<br><small>Grade {{$profile->grade}}</small>@endif</td>
                        <td><span class="label {{in_array($profile->employment_status,['active','confirmed'])?'bg-green':($profile->employment_status==='terminated'?'bg-gray':'bg-yellow')}}">{{ucwords(str_replace('_',' ',$profile->employment_status))}}</span></td>
                        <td>{{$profile->manager->user->user_full_name ?? '-'}}</td>
                        <td class="text-right"><a class="btn btn-xs btn-info" href="{{route('hrm.employment.show',$profile->id)}}"><i class="fa fa-eye"></i> View</a> @can('essentials.manage_employee_profiles')<a class="btn btn-xs btn-primary" href="{{route('hrm.employment.edit',$profile->id)}}"><i class="fa fa-edit"></i> Edit</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No employee profile matches the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{$profiles->links()}}
    @endcomponent
</section>
@endsection
