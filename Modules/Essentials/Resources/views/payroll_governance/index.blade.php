@extends('layouts.app')
@section('title', 'Payroll Control')
@section('content')
@php
    $payrollBusinessId = (int) session()->get('user.business_id');
    $canManagePayrollRuns = auth()->user()->can('superadmin') || auth()->user()->hasRole('Admin#'.$payrollBusinessId) || auth()->user()->canForBusiness('essentials.manage_payroll_runs', $payrollBusinessId);
@endphp
@include('essentials::layouts.nav_hrm')
<section class="content-header"><h1>Payroll Control <small>Governed calculation, approval and payment</small></h1></section>
<section class="content">
    <div class="row">
        @foreach(['draft'=>'Draft or calculated','awaiting_approval'=>'Awaiting approval','ready_to_pay'=>'Ready to post or pay','paid'=>'Paid or closed'] as $key=>$label)
            <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-money"></i></span><div class="info-box-content"><span class="info-box-text">{{$label}}</span><span class="info-box-number">{{$summary[$key]}}</span></div></div></div>
        @endforeach
    </div>
    @if($canManagePayrollRuns)
    <div class="row">
        <div class="col-md-4">
            @component('components.widget', ['class'=>'box-primary','title'=>'1. Payroll period'])
                {!! Form::open(['route'=>'hrm.payroll-periods.store']) !!}
                <div class="form-group">{!! Form::label('code','Unique code *') !!}{!! Form::text('code',null,['class'=>'form-control','required','placeholder'=>'2026-09-MONTHLY']) !!}</div>
                <div class="form-group">{!! Form::label('name','Period name *') !!}{!! Form::text('name',null,['class'=>'form-control','required']) !!}</div>
                <div class="row"><div class="col-xs-6"><div class="form-group">{!! Form::label('starts_on','Starts *') !!}{!! Form::date('starts_on',null,['class'=>'form-control','required']) !!}</div></div><div class="col-xs-6"><div class="form-group">{!! Form::label('ends_on','Ends *') !!}{!! Form::date('ends_on',null,['class'=>'form-control','required']) !!}</div></div></div>
                <div class="form-group">{!! Form::label('payment_date','Payment date') !!}{!! Form::date('payment_date',null,['class'=>'form-control']) !!}</div>
                <button class="btn btn-primary"><i class="fa fa-calendar-plus-o"></i> Add period</button>
                {!! Form::close() !!}
            @endcomponent
        </div>
        <div class="col-md-4">
            @component('components.widget', ['class'=>'box-primary','title'=>'2. Country rules pack'])
                <p class="text-muted small">Country packs are versioned configuration. Activate only after a qualified local payroll or tax professional reviews the rules.</p>
                {!! Form::open(['route'=>'hrm.payroll-country-packs.store']) !!}
                <div class="row"><div class="col-xs-4"><div class="form-group">{!! Form::label('country_code','Country *') !!}{!! Form::text('country_code',null,['class'=>'form-control','required','maxlength'=>2,'placeholder'=>'KE']) !!}</div></div><div class="col-xs-8"><div class="form-group">{!! Form::label('jurisdiction','Jurisdiction') !!}{!! Form::text('jurisdiction',null,['class'=>'form-control']) !!}</div></div></div>
                <div class="form-group">{!! Form::label('name','Pack name *') !!}{!! Form::text('name',null,['class'=>'form-control','required']) !!}</div>
                <div class="row"><div class="col-xs-6"><div class="form-group">{!! Form::label('version','Version *') !!}{!! Form::text('version',null,['class'=>'form-control','required','placeholder'=>'2026.1']) !!}</div></div><div class="col-xs-6"><div class="form-group">{!! Form::label('effective_from','Effective from *') !!}{!! Form::date('effective_from',null,['class'=>'form-control','required']) !!}</div></div></div>
                <div class="form-group">{!! Form::label('rules_json','Validated rules JSON *') !!}{!! Form::textarea('rules_json','{"employee_deductions":[]}', ['class'=>'form-control','rows'=>3,'required']) !!}</div>
                <div class="form-group">{!! Form::label('reviewed_by','Professional reviewer') !!}{!! Form::text('reviewed_by',null,['class'=>'form-control']) !!}</div>
                <label class="checkbox-inline">{!! Form::checkbox('professionally_reviewed',1,false) !!} Professionally reviewed</label>
                <label class="checkbox-inline">{!! Form::checkbox('is_active',1,false) !!} Activate</label><br><br>
                <button class="btn btn-primary"><i class="fa fa-shield"></i> Save pack</button>
                {!! Form::close() !!}
            @endcomponent
        </div>
        <div class="col-md-4">
            @component('components.widget', ['class'=>'box-primary','title'=>'3. New payroll run'])
                {!! Form::open(['route'=>'hrm.payroll-runs.store']) !!}
                <div class="form-group">{!! Form::label('payroll_period_id','Open period *') !!}{!! Form::select('payroll_period_id',$periods->where('status','open')->pluck('name','id'),null,['class'=>'form-control select2','required','placeholder'=>'Select period']) !!}</div>
                <div class="form-group">{!! Form::label('country_pack_id','Reviewed country pack') !!}{!! Form::select('country_pack_id',$countryPacks->where('is_active',true)->where('professionally_reviewed',true)->pluck('name','id'),null,['class'=>'form-control select2','placeholder'=>'No statutory pack']) !!}</div>
                <div class="form-group">{!! Form::label('name','Run name *') !!}{!! Form::text('name',null,['class'=>'form-control','required']) !!}</div>
                <div class="form-group">{!! Form::label('run_type','Run type *') !!}{!! Form::select('run_type',['regular'=>'Regular','off_cycle'=>'Off-cycle','final'=>'Final pay','correction'=>'Correction'],'regular',['class'=>'form-control','required']) !!}</div>
                <div class="form-group">{!! Form::label('notes','Notes') !!}{!! Form::textarea('notes',null,['class'=>'form-control','rows'=>2]) !!}</div>
                <button class="btn btn-primary"><i class="fa fa-plus"></i> Create run</button>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
    @endif
    @component('components.widget', ['class'=>'box-primary','title'=>'Payroll runs'])
        <div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Run</th><th>Period</th><th>Status</th><th>Employees</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">Net</th><th></th></tr></thead><tbody>
        @forelse($runs as $run)
            <tr><td><strong>{{$run->name}}</strong><br><small>{{$run->run_type}} · {{$run->currency_code}}</small></td><td>{{$run->period->name ?? '-'}}</td><td><span class="label {{in_array($run->status,['paid','closed'])?'bg-green':(in_array($run->status,['reviewed','approved','posted'])?'bg-yellow':'bg-gray')}}">{{ucwords(str_replace('_',' ',$run->status))}}</span></td><td>{{$run->employee_count}}</td><td class="text-right">{{number_format($run->gross_total,2)}}</td><td class="text-right">{{number_format($run->deduction_total,2)}}</td><td class="text-right"><strong>{{number_format($run->net_total,2)}}</strong></td><td><a class="btn btn-xs btn-info" href="{{route('hrm.payroll-runs.show',$run->id)}}"><i class="fa fa-eye"></i> Open</a></td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted">No governed payroll runs have been created.</td></tr>@endforelse
        </tbody></table></div>{{$runs->links()}}
    @endcomponent
    <div class="row"><div class="col-md-6">@component('components.widget',['title'=>'Payroll periods'])<div class="table-responsive"><table class="table table-condensed"><thead><tr><th>Code</th><th>Dates</th><th>Status</th></tr></thead><tbody>@forelse($periods as $period)<tr><td>{{$period->code}}</td><td>{{$period->starts_on->format('Y-m-d')}} – {{$period->ends_on->format('Y-m-d')}}</td><td>{{$period->status}}</td></tr>@empty<tr><td colspan="3" class="text-muted">No periods.</td></tr>@endforelse</tbody></table></div>@endcomponent</div>
    <div class="col-md-6">@component('components.widget',['title'=>'Country packs'])<div class="table-responsive"><table class="table table-condensed"><thead><tr><th>Country</th><th>Name</th><th>Version</th><th>Control</th></tr></thead><tbody>@forelse($countryPacks as $pack)<tr><td>{{$pack->country_code}}</td><td>{{$pack->name}}</td><td>{{$pack->version}}</td><td>@if($pack->professionally_reviewed)<span class="label bg-green">Reviewed</span>@else<span class="label bg-yellow">Draft</span>@endif @if($pack->is_active)<span class="label bg-blue">Active</span>@endif</td></tr>@empty<tr><td colspan="4" class="text-muted">No country packs.</td></tr>@endforelse</tbody></table></div>@endcomponent</div></div>
</section>
@endsection
