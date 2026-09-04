@extends('layouts.app')
@section('title', 'Workforce Operations')
@section('content')
@include('essentials::layouts.nav_hrm')
<section class="content-header">
    <h1>Workforce Operations <small>Time, leave, documents and employee lifecycle</small></h1>
</section>
<section class="content">
    <div class="row">
        @foreach(['pending_corrections'=>'Pending corrections','expiring_documents'=>'Documents expiring in 60 days','open_checklists'=>'Open checklists','active_calendars'=>'Active calendars'] as $key=>$label)
            <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-clock-o"></i></span><div class="info-box-content"><span class="info-box-text">{{$label}}</span><span class="info-box-number">{{$summary[$key]}}</span></div></div></div>
        @endforeach
    </div>

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#time" data-toggle="tab">Time corrections</a></li>
            <li><a href="#leave-ledger" data-toggle="tab">Leave balances</a></li>
            <li><a href="#documents" data-toggle="tab">Documents</a></li>
            <li><a href="#lifecycle" data-toggle="tab">Lifecycle</a></li>
            @if($isManager)<li><a href="#calendars" data-toggle="tab">Work calendars</a></li>@endif
        </ul>
        <div class="tab-content">
            <div class="tab-pane active" id="time">
                @if($ownProfile || $isManager)
                    @component('components.widget', ['class'=>'box-primary','title'=>'Request a time correction'])
                        {!! Form::open(['route'=>'hrm.workforce.time-corrections.store']) !!}
                        <div class="row">
                            @if($isManager)
                                <div class="col-md-3"><div class="form-group">{!! Form::label('employment_profile_id','Employee') !!}{!! Form::select('employment_profile_id',$profiles->pluck('user.user_full_name','id'),null,['class'=>'form-control select2','placeholder'=>'My own record']) !!}</div></div>
                            @endif
                            <div class="col-md-2"><div class="form-group">{!! Form::label('attendance_id','Attendance ID') !!}{!! Form::number('attendance_id',null,['class'=>'form-control','min'=>1,'placeholder'=>'Optional']) !!}</div></div>
                            <div class="col-md-2"><div class="form-group">{!! Form::label('correction_type','Correction *') !!}{!! Form::select('correction_type',['missed_clock_in'=>'Missed clock in','missed_clock_out'=>'Missed clock out','time_change'=>'Change times','break_change'=>'Break change','overtime'=>'Overtime'],'time_change',['class'=>'form-control','required']) !!}</div></div>
                            <div class="col-md-2"><div class="form-group">{!! Form::label('requested_clock_in','Requested in') !!}{!! Form::input('datetime-local','requested_clock_in',null,['class'=>'form-control']) !!}</div></div>
                            <div class="col-md-2"><div class="form-group">{!! Form::label('requested_clock_out','Requested out') !!}{!! Form::input('datetime-local','requested_clock_out',null,['class'=>'form-control']) !!}</div></div>
                            <div class="col-md-1"><div class="form-group">{!! Form::label('overtime_minutes','OT min') !!}{!! Form::number('overtime_minutes',0,['class'=>'form-control','min'=>0,'max'=>1440]) !!}</div></div>
                            <div class="col-md-10"><div class="form-group">{!! Form::label('reason','Reason *') !!}{!! Form::text('reason',null,['class'=>'form-control','required','maxlength'=>2000]) !!}</div></div>
                            <div class="col-md-2"><label>&nbsp;</label><button class="btn btn-primary btn-block">Submit request</button></div>
                        </div>
                        {!! Form::close() !!}
                    @endcomponent
                @endif
                <div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Employee</th><th>Type</th><th>Requested times</th><th>Reason</th><th>Status</th><th>Decision</th></tr></thead><tbody>
                    @forelse($corrections as $item)
                        <tr><td>{{$item->profile->user->user_full_name ?? '-'}}</td><td>{{ucwords(str_replace('_',' ',$item->correction_type))}}</td><td>{{$item->requested_clock_in ?: '-'}} – {{$item->requested_clock_out ?: '-'}}</td><td>{{$item->reason}}</td><td><span class="label {{$item->status==='approved'?'bg-green':($item->status==='rejected'?'bg-red':'bg-yellow')}}">{{$item->status}}</span></td><td>
                        @if($isManager && $item->status==='pending')
                            {!! Form::open(['route'=>['hrm.workforce.time-corrections.decision',$item->id],'class'=>'form-inline']) !!}{!! Form::select('decision',['approved'=>'Approve','rejected'=>'Reject'],null,['class'=>'form-control input-sm']) !!} {!! Form::text('decision_note',null,['class'=>'form-control input-sm','required','placeholder'=>'Decision note']) !!} <button class="btn btn-xs btn-primary">Record</button>{!! Form::close() !!}
                        @else
                            {{$item->decision_note ?: '-'}}
                        @endif
                        </td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No time corrections.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="tab-pane" id="leave-ledger">
                @if($isManager)
                    @component('components.widget', ['class'=>'box-primary','title'=>'Create leave account'])
                        {!! Form::open(['route'=>'hrm.workforce.leave-accounts.store','class'=>'row']) !!}
                        <div class="col-md-3"><div class="form-group">{!! Form::label('employment_profile_id','Employee *') !!}{!! Form::select('employment_profile_id',$profiles->pluck('user.user_full_name','id'),null,['class'=>'form-control select2','required','placeholder'=>'Select employee']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('leave_type_id','Leave type *') !!}{!! Form::select('leave_type_id',$leaveTypes,null,['class'=>'form-control select2','required','placeholder'=>'Select type']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('policy_period','Policy period *') !!}{!! Form::text('policy_period',date('Y'),['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('opening_balance','Opening balance *') !!}{!! Form::number('opening_balance',0,['class'=>'form-control','step'=>'0.001','min'=>0,'required']) !!}</div></div>
                        <div class="col-md-2"><label>&nbsp;</label><button class="btn btn-primary btn-block">Create</button></div>
                        {!! Form::close() !!}
                    @endcomponent
                @endif
                <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Employee</th><th>Leave type</th><th>Period</th><th class="text-right">Opening</th><th class="text-right">Accrued</th><th class="text-right">Reserved</th><th class="text-right">Used</th><th class="text-right">Available</th><th>Adjustment</th></tr></thead><tbody>
                    @forelse($leaveAccounts as $account)
                        <tr><td>{{$account->profile->user->user_full_name ?? '-'}}</td><td>{{$account->leaveType->leave_type ?? '-'}}</td><td>{{$account->policy_period}}</td><td class="text-right">{{$account->opening_balance}}</td><td class="text-right">{{$account->accrued}}</td><td class="text-right">{{$account->reserved}}</td><td class="text-right">{{$account->used}}</td><td class="text-right"><strong>{{number_format($account->availableBalance(),3)}}</strong></td><td>
                        @if($isManager)
                            {!! Form::open(['route'=>['hrm.workforce.leave-ledger.store',$account->id],'class'=>'form-inline']) !!}{!! Form::select('entry_type',['accrual'=>'Accrue','carry_forward'=>'Carry forward','adjustment'=>'Adjust','reservation'=>'Reserve','release'=>'Release','usage'=>'Use','expiry'=>'Expire'],null,['class'=>'form-control input-sm']) !!} {!! Form::number('quantity',null,['class'=>'form-control input-sm','step'=>'0.001','required','style'=>'width:85px']) !!} {!! Form::date('effective_date',date('Y-m-d'),['class'=>'form-control input-sm','required']) !!} {!! Form::text('reason',null,['class'=>'form-control input-sm','required','placeholder'=>'Reason']) !!} <button class="btn btn-xs btn-primary">Post</button>{!! Form::close() !!}
                        @else
                            —
                        @endif
                        </td></tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No leave accounts.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="tab-pane" id="documents">
                @if(auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.session()->get('user.business_id')) || auth()->user()->canForBusiness('essentials.manage_hr_documents', (int) session()->get('user.business_id')))
                    @component('components.widget', ['class'=>'box-primary','title'=>'Secure employee document'])
                        {!! Form::open(['route'=>'hrm.workforce.documents.store','files'=>true,'class'=>'row']) !!}
                        <div class="col-md-3"><div class="form-group">{!! Form::label('employment_profile_id','Employee *') !!}{!! Form::select('employment_profile_id',$profiles->pluck('user.user_full_name','id'),null,['class'=>'form-control select2','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('document_type','Type *') !!}{!! Form::text('document_type',null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('name','Name *') !!}{!! Form::text('name',null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('access_level','Access *') !!}{!! Form::select('access_level',['employee'=>'Employee','employee_hr'=>'Employee and HR','hr_only'=>'HR only','restricted'=>'Restricted'],'employee_hr',['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('document','File *') !!}{!! Form::file('document',['class'=>'form-control','required','accept'=>'.pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('issued_on','Issued') !!}{!! Form::date('issued_on',null,['class'=>'form-control']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('expires_on','Expires') !!}{!! Form::date('expires_on',null,['class'=>'form-control']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('retention_until','Retain until') !!}{!! Form::date('retention_until',null,['class'=>'form-control']) !!}</div></div>
                        <div class="col-md-3"><label>&nbsp;</label><button class="btn btn-primary btn-block">Upload securely</button></div>
                        {!! Form::close() !!}
                    @endcomponent
                @endif
                <div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Employee</th><th>Document</th><th>Type</th><th>Access</th><th>Expiry</th><th></th></tr></thead><tbody>
                    @forelse($documents as $document)
                        <tr><td>{{$document->profile->user->user_full_name ?? '-'}}</td><td>{{$document->name}} <small>v{{$document->version}}</small></td><td>{{$document->document_type}}</td><td>{{$document->access_level}}</td><td>{{$document->expires_on ? $document->expires_on->format('Y-m-d') : '-'}}</td><td><a class="btn btn-xs btn-info" href="{{route('hrm.workforce.documents.download',$document->id)}}"><i class="fa fa-download"></i> Download</a></td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No accessible employee documents.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="tab-pane" id="lifecycle">
                @if($isManager)
                    @component('components.widget', ['class'=>'box-primary','title'=>'Start employee lifecycle event'])
                        {!! Form::open(['route'=>'hrm.workforce.lifecycle.store','class'=>'row']) !!}
                        <div class="col-md-3"><div class="form-group">{!! Form::label('employment_profile_id','Employee *') !!}{!! Form::select('employment_profile_id',$profiles->pluck('user.user_full_name','id'),null,['class'=>'form-control select2','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('event_type','Event *') !!}{!! Form::select('event_type',['onboarding'=>'Onboarding','probation'=>'Probation','confirmation'=>'Confirmation','transfer'=>'Transfer','promotion'=>'Promotion','return_to_work'=>'Return to work','offboarding'=>'Offboarding'],null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('effective_date','Effective date *') !!}{!! Form::date('effective_date',null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('reason','Reason *') !!}{!! Form::text('reason',null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><label>&nbsp;</label><button class="btn btn-primary btn-block">Create event</button></div>
                        {!! Form::close() !!}
                    @endcomponent
                @endif
                @forelse($lifecycleEvents as $event)
                    @component('components.widget', ['title'=>ucwords(str_replace('_',' ',$event->event_type)).' · '.($event->profile->user->user_full_name ?? '-')])
                        <p><strong>Effective:</strong> {{$event->effective_date->format('Y-m-d')}} &nbsp; <strong>Status:</strong> {{$event->status}} &nbsp; {{$event->reason}}</p>
                        @if($isManager && $event->status==='planned' && !$event->effective_date->isFuture() && !$event->checklists->flatMap->tasks->contains(fn($task) => $task->status!=='completed'))
                            {!! Form::open(['route'=>['hrm.workforce.lifecycle.complete',$event->id],'style'=>'margin-bottom:10px']) !!}<button class="btn btn-sm btn-primary" onclick="return confirm('Apply this lifecycle event to the employment profile?')"><i class="fa fa-check-circle"></i> Complete and apply event</button>{!! Form::close() !!}
                        @endif
                        @foreach($event->checklists as $checklist)
                            <h5><strong>{{$checklist->name}}</strong> <span class="label {{$checklist->status==='completed'?'bg-green':'bg-yellow'}}">{{$checklist->status}}</span></h5>
                            <ul class="list-group">
                                @foreach($checklist->tasks as $task)
                                    <li class="list-group-item">{{$task->title}}
                                        @if($task->status==='completed')
                                            <span class="pull-right text-success"><i class="fa fa-check"></i> Completed</span>
                                        @elseif($isManager)
                                            <span class="pull-right">{!! Form::open(['route'=>['hrm.workforce.checklist-tasks.complete',$task->id]]) !!}<button class="btn btn-xs btn-success">Complete</button>{!! Form::close() !!}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    @endcomponent
                @empty
                    <p class="text-muted text-center">No lifecycle events.</p>
                @endforelse
            </div>

            @if($isManager)
                <div class="tab-pane" id="calendars">
                    @component('components.widget', ['class'=>'box-primary','title'=>'Create work calendar'])
                        <p class="text-muted small">Store schedules with an explicit IANA timezone so daylight-saving and cross-country operations remain predictable.</p>
                        {!! Form::open(['route'=>'hrm.workforce.calendars.store','class'=>'row']) !!}
                        <div class="col-md-3"><div class="form-group">{!! Form::label('name','Name *') !!}{!! Form::text('name',null,['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('location_id','Location') !!}{!! Form::select('location_id',$locations,null,['class'=>'form-control select2','placeholder'=>'All locations']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('timezone','Timezone *') !!}{!! Form::text('timezone',config('app.timezone'),['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-2"><div class="form-group">{!! Form::label('standard_hours_per_week','Hours/week *') !!}{!! Form::number('standard_hours_per_week',40,['class'=>'form-control','step'=>'0.25','min'=>1,'max'=>168,'required']) !!}</div></div>
                        <div class="col-md-3"><div class="form-group">{!! Form::label('weekly_schedule_json','Weekly schedule JSON *') !!}{!! Form::text('weekly_schedule_json','{"monday":{"start":"09:00","end":"17:00"},"tuesday":{"start":"09:00","end":"17:00"},"wednesday":{"start":"09:00","end":"17:00"},"thursday":{"start":"09:00","end":"17:00"},"friday":{"start":"09:00","end":"17:00"}}',['class'=>'form-control','required']) !!}</div></div>
                        <div class="col-md-9"><label class="checkbox-inline">{!! Form::checkbox('is_default',1,false) !!} Default calendar</label><label class="checkbox-inline">{!! Form::checkbox('is_active',1,true) !!} Active</label></div>
                        <div class="col-md-3"><button class="btn btn-primary btn-block">Create calendar</button></div>
                        {!! Form::close() !!}
                    @endcomponent
                    <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Name</th><th>Timezone</th><th>Hours/week</th><th>Scope</th><th>Status</th></tr></thead><tbody>
                        @forelse($calendars as $calendar)
                            <tr><td>{{$calendar->name}}</td><td>{{$calendar->timezone}}</td><td>{{$calendar->standard_hours_per_week}}</td><td>{{$calendar->location_id ? ($locations[$calendar->location_id] ?? 'Location') : 'Company'}}</td><td>@if($calendar->is_default)<span class="label bg-blue">Default</span>@endif {{$calendar->is_active?'Active':'Inactive'}}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-muted text-center">No work calendars.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            @endif
        </div>
    </div>

    @if($activeSurveys->isNotEmpty())
        @component('components.widget', ['class'=>'box-primary','title'=>'My engagement surveys'])
            <p class="text-muted small">Each survey accepts one response. Anonymous surveys store a one-way participation token instead of your employee identity.</p>
            @foreach($activeSurveys as $survey)
                @php($questions=json_decode($survey->questions,true) ?: [])
                <div class="well well-sm"><h4>{{$survey->name}} <small>Closes {{$survey->closes_on}} · {{$survey->is_anonymous?'Anonymous':'Identified'}}</small></h4>
                    {!! Form::open(['route'=>['hrm.workforce.surveys.respond',$survey->id]]) !!}
                    <div class="row">
                        @foreach($questions as $question)
                            @if(!empty($question['id']))
                                <div class="col-md-6"><div class="form-group"><label>{{($question['text'] ?? $question['id']).' *'}}</label>
                                @if(($question['type'] ?? 'text')==='scale')
                                    {!! Form::select('answers['.$question['id'].']',[1=>'1 - Strongly disagree',2=>'2',3=>'3',4=>'4',5=>'5 - Strongly agree'],null,['class'=>'form-control','required','placeholder'=>'Select']) !!}
                                @else
                                    {!! Form::textarea('answers['.$question['id'].']',null,['class'=>'form-control','rows'=>2,'required','maxlength'=>2000]) !!}
                                @endif
                                </div></div>
                            @endif
                        @endforeach
                    </div>
                    <button class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit response</button>
                    {!! Form::close() !!}
                </div>
            @endforeach
        @endcomponent
    @endif
</section>
@endsection
