@extends('layouts.app')
@section('title', 'Payroll Run')
@section('content')
@php
    $payrollBusinessId = (int) session()->get('user.business_id');
    $payrollAdministrator = auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.$payrollBusinessId);
    $canManagePayrollRuns = $payrollAdministrator || auth()->user()->canForBusiness('essentials.manage_payroll_runs', $payrollBusinessId);
    $canApprovePayrollRuns = $payrollAdministrator || auth()->user()->canForBusiness('essentials.approve_payroll_runs', $payrollBusinessId);
    $canPayPayroll = $payrollAdministrator || auth()->user()->canForBusiness('essentials.pay_payroll', $payrollBusinessId);
@endphp
@include('essentials::layouts.nav_hrm')
<section class="content-header"><h1>{{$record->name}} <small>{{$record->period->name}} · {{$record->currency_code}}</small></h1></section>
<section class="content">
    <div class="row"><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Employees</span><span class="info-box-number">{{$record->employee_count}}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-plus"></i></span><div class="info-box-content"><span class="info-box-text">Gross</span><span class="info-box-number">{{number_format($record->gross_total,2)}}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-minus"></i></span><div class="info-box-content"><span class="info-box-text">Deductions</span><span class="info-box-number">{{number_format($record->deduction_total,2)}}</span></div></div></div><div class="col-md-3"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-money"></i></span><div class="info-box-content"><span class="info-box-text">Net</span><span class="info-box-number">{{number_format($record->net_total,2)}}</span></div></div></div></div>
    @component('components.widget',['class'=>'box-primary','title'=>'Workflow'])
        <p><strong>Status:</strong> <span class="label bg-blue">{{ucwords(str_replace('_',' ',$record->status))}}</span> &nbsp; <strong>Formula:</strong> {{$record->formula_version}} &nbsp; <strong>Country pack:</strong> {{$record->countryPack ? $record->countryPack->name.' '.$record->countryPack->version : 'Not assigned'}}</p>
        <div class="btn-group">
        @if($canManagePayrollRuns)
            @if(in_array($record->status,['draft','calculated'])){!! Form::open(['route'=>['hrm.payroll-runs.calculate',$record->id],'style'=>'display:inline']) !!}<button class="btn btn-primary"><i class="fa fa-calculator"></i> Calculate on server</button>{!! Form::close() !!}@endif
            @if($record->status==='calculated'){!! Form::open(['route'=>['hrm.payroll-runs.transition',$record->id],'style'=>'display:inline']) !!}{!! Form::hidden('action','review') !!}<button class="btn btn-warning"><i class="fa fa-check-square-o"></i> Submit review</button>{!! Form::close() !!}@endif
        @endif
        </div>
        @if($canApprovePayrollRuns)
            @if(in_array($record->status,['reviewed','approved','paid']))
            {!! Form::open(['route'=>['hrm.payroll-runs.transition',$record->id],'class'=>'form-inline','style'=>'margin-top:10px']) !!}
                <div class="form-group">{!! Form::select('action',$record->status==='reviewed'?['approve'=>'Approve','reject'=>'Reject']:($record->status==='approved'?['post'=>'Post','reject'=>'Reject']:['close'=>'Close']),null,['class'=>'form-control','required']) !!}</div>
                <div class="form-group">{!! Form::text('comment',null,['class'=>'form-control','placeholder'=>'Comment or required rejection reason','style'=>'min-width:320px']) !!}</div>
                <button class="btn btn-success">Apply controlled transition</button>
            {!! Form::close() !!}
            @endif
        @endif
    @endcomponent
    @component('components.widget',['class'=>'box-primary','title'=>'One-time payroll inputs'])
        @if($canManagePayrollRuns)
            @if(in_array($record->status,['draft','calculated']))
                {!! Form::open(['route'=>['hrm.payroll-runs.inputs.store',$record->id],'class'=>'row']) !!}
                <div class="col-md-3"><div class="form-group">{!! Form::label('employment_profile_id','Employee *') !!}{!! Form::select('employment_profile_id',$profiles->pluck('user.user_full_name','id'),null,['class'=>'form-control select2','required']) !!}</div></div>
                <div class="col-md-2"><div class="form-group">{!! Form::label('input_type','Type *') !!}{!! Form::select('input_type',['earning'=>'Earning','deduction'=>'Deduction'],null,['class'=>'form-control','required']) !!}</div></div>
                <div class="col-md-1"><div class="form-group">{!! Form::label('code','Code *') !!}{!! Form::text('code',null,['class'=>'form-control','required']) !!}</div></div>
                <div class="col-md-2"><div class="form-group">{!! Form::label('label','Label *') !!}{!! Form::text('label',null,['class'=>'form-control','required']) !!}</div></div>
                <div class="col-md-1"><div class="form-group">{!! Form::label('quantity','Quantity *') !!}{!! Form::number('quantity',1,['class'=>'form-control','step'=>'0.0001','min'=>0.0001,'required']) !!}</div></div>
                <div class="col-md-1"><div class="form-group">{!! Form::label('rate','Rate') !!}{!! Form::number('rate',null,['class'=>'form-control','step'=>'0.0001','min'=>0]) !!}</div></div>
                <div class="col-md-1"><div class="form-group">{!! Form::label('amount','Amount') !!}{!! Form::number('amount',null,['class'=>'form-control','step'=>'0.0001','min'=>0]) !!}</div></div>
                <div class="col-md-1">{!! Form::hidden('source','manual') !!}<label>&nbsp;</label><button class="btn btn-primary btn-block">Add</button></div>
                {!! Form::close() !!}
            @endif
        @endif
        <div class="table-responsive"><table class="table table-condensed"><thead><tr><th>Employee</th><th>Input</th><th>Source</th><th class="text-right">Amount</th><th>Status / decision</th></tr></thead><tbody>
        @forelse($record->inputs as $input)
            <tr><td>{{$input->profile->user->user_full_name ?? '-'}}</td><td><strong>{{$input->code}}</strong> · {{$input->label}} <span class="label {{$input->input_type==='earning'?'bg-green':'bg-yellow'}}">{{$input->input_type}}</span></td><td>{{$input->source}}</td><td class="text-right">{{number_format($input->amount,2)}}</td><td><span class="label {{$input->status==='approved'?'bg-green':($input->status==='rejected'?'bg-red':'bg-yellow')}}">{{$input->status}}</span>
            @if($canApprovePayrollRuns)
                @if($input->status==='pending')
                    {!! Form::open(['route'=>['hrm.payroll-inputs.decision',$input->id],'class'=>'form-inline','style'=>'display:inline']) !!}{!! Form::select('decision',['approved'=>'Approve','rejected'=>'Reject'],null,['class'=>'form-control input-sm']) !!} {!! Form::text('reason',null,['class'=>'form-control input-sm','placeholder'=>'Reason if rejected']) !!} <button class="btn btn-xs btn-primary">Record</button>{!! Form::close() !!}
                @endif
            @endif
            </td></tr>
        @empty
            <tr><td colspan="5" class="text-muted text-center">No one-time inputs. Payroll will use each employment profile’s recurring compensation.</td></tr>
        @endforelse
        </tbody></table></div>
    @endcomponent
    @component('components.widget',['class'=>'box-primary','title'=>'Calculated employees'])
        <div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Employee</th><th>Employee no.</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">Net</th><th class="text-right">Variance</th><th>Alerts</th></tr></thead><tbody>
        @forelse($record->items as $item)<tr><td>{{$item->profile->user->user_full_name ?? data_get($item->employment_snapshot,'name','-')}}</td><td>{{$item->employee_number}}</td><td class="text-right">{{number_format($item->gross_amount,2)}}</td><td class="text-right">{{number_format($item->deduction_amount,2)}}</td><td class="text-right"><strong>{{number_format($item->net_amount,2)}}</strong></td><td class="text-right">{{$item->variance_percent===null?'-':number_format($item->variance_percent,2).'%'}}</td><td>@forelse((array)$item->alerts as $alert)<span class="label bg-yellow" title="{{$alert['message'] ?? ''}}">{{$alert['code'] ?? 'Alert'}}</span> @empty<span class="text-success">Clear</span>@endforelse</td></tr>@empty<tr><td colspan="7" class="text-center text-muted">Calculate the run to produce immutable employee snapshots.</td></tr>@endforelse
        </tbody></table></div>
    @endcomponent
    <div class="row"><div class="col-md-6">
    @component('components.widget',['title'=>'Approval history'])
        <table class="table table-condensed"><thead><tr><th>Stage</th><th>Decision</th><th>Actor</th><th>When</th><th>Comment</th></tr></thead><tbody>@forelse($record->approvals as $approval)<tr><td>{{$approval->stage}}</td><td>{{$approval->decision}}</td><td>{{$approval->actor->user_full_name ?? $approval->actor_user_id}}</td><td>{{$approval->decided_at}}</td><td>{{$approval->comment ?: '-'}}</td></tr>@empty<tr><td colspan="5" class="text-muted">No decisions yet.</td></tr>@endforelse</tbody></table>
    @endcomponent</div><div class="col-md-6">
    @component('components.widget',['title'=>'Payment control'])
        @if($canPayPayroll)
        @if($record->status==='posted' && $record->paymentBatches->isEmpty())
            {!! Form::open(['route'=>['hrm.payroll-runs.payment-batches.store',$record->id]]) !!}<div class="row"><div class="col-sm-5"><div class="form-group">{!! Form::label('reference','Batch reference *') !!}{!! Form::text('reference',null,['class'=>'form-control','required']) !!}</div></div><div class="col-sm-4"><div class="form-group">{!! Form::label('payment_method','Method *') !!}{!! Form::select('payment_method',['bank_transfer'=>'Bank transfer','mobile_money'=>'Mobile money','cheque'=>'Cheque','cash'=>'Cash','other'=>'Other'],null,['class'=>'form-control','required']) !!}</div></div><div class="col-sm-3"><label>&nbsp;</label><button class="btn btn-primary btn-block">Prepare</button></div></div>{!! Form::close() !!}
        @endif
        @foreach($record->paymentBatches as $batch)
            <p><strong>{{$batch->reference}}</strong> · {{number_format($batch->amount,2)}} · <span class="label bg-blue">{{$batch->status}}</span></p>
            @if(in_array($batch->status,['prepared','submitted'])){!! Form::open(['route'=>['hrm.payroll-payment-batches.reconcile',$batch->id],'class'=>'form-inline']) !!}{!! Form::select('status',['submitted'=>'Submitted','reconciled'=>'Reconciled / paid','failed'=>'Failed'],null,['class'=>'form-control']) !!} {!! Form::text('provider_reference',null,['class'=>'form-control','placeholder'=>'Provider reference']) !!} {!! Form::text('note',null,['class'=>'form-control','placeholder'=>'Reconciliation note']) !!} <button class="btn btn-success">Record</button>{!! Form::close() !!}@endif
        @endforeach
        @endif
    @endcomponent</div></div>
    <a href="{{route('hrm.payroll-runs.index')}}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Back to Payroll Control</a>
</section>
@endsection
