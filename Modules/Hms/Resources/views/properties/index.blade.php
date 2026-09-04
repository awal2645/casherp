@extends('layouts.app')
@section('title', __('hms::lang.properties'))
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>@lang('hms::lang.properties') <small>@lang('hms::lang.properties_help')</small></h1></section>
<section class="content">
    <div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">@lang('hms::lang.add_property')</h3></div><div class="box-body">
        <p class="help-block">@lang('hms::lang.property_plan_usage', ['used' => $properties->count(), 'limit' => $propertyLimit === 0 ? __('hms::lang.unlimited') : $propertyLimit])</p>
        {!! Form::open(['route' => 'hms.properties.store']) !!}
        <div class="row">
            <div class="col-md-3"><div class="form-group">{!! Form::label('name', __('hms::lang.property_name').':*') !!}{!! Form::text('name', null, ['class'=>'form-control','required','maxlength'=>191]) !!}</div></div>
            <div class="col-md-2"><div class="form-group">{!! Form::label('code', __('hms::lang.code').':*') !!}{!! Form::text('code', null, ['class'=>'form-control','required','maxlength'=>40]) !!}</div></div>
            <div class="col-md-2"><div class="form-group">{!! Form::label('location_id', __('business.business_location').':') !!}{!! Form::select('location_id', $locations, null, ['class'=>'form-control select2','placeholder'=>__('lang_v1.none')]) !!}</div></div>
            <div class="col-md-2"><div class="form-group">{!! Form::label('currency_id', __('business.currency').':') !!}{!! Form::select('currency_id', $currencies, $business->currency_id, ['class'=>'form-control select2']) !!}</div></div>
            <div class="col-md-3"><div class="form-group">{!! Form::label('timezone', __('business.time_zone').':*') !!}{!! Form::select('timezone', array_combine(timezone_identifiers_list(), timezone_identifiers_list()), $business->time_zone, ['class'=>'form-control select2','required']) !!}</div></div>
            <div class="col-md-3"><div class="form-group">{!! Form::label('public_slug', __('hms::lang.public_booking_slug').':') !!}{!! Form::text('public_slug', null, ['class'=>'form-control','maxlength'=>191]) !!}</div></div>
            <div class="col-md-2"><div class="form-group">{!! Form::label('default_check_in_time', __('hms::lang.default_check_in').':*') !!}{!! Form::time('default_check_in_time', '14:00', ['class'=>'form-control','required']) !!}</div></div>
            <div class="col-md-2"><div class="form-group">{!! Form::label('default_check_out_time', __('hms::lang.default_check_out').':*') !!}{!! Form::time('default_check_out_time', '11:00', ['class'=>'form-control','required']) !!}</div></div>
            <div class="col-md-3"><div class="checkbox" style="margin-top:25px"><label>{!! Form::checkbox('booking_engine_enabled', 1, false) !!} @lang('hms::lang.enable_booking_engine')</label></div></div>
            <div class="col-md-2"><div class="checkbox" style="margin-top:25px"><label>{!! Form::checkbox('is_active', 1, true) !!} @lang('hms::lang.active')</label></div></div>
        </div><button class="btn btn-primary"><i class="fa fa-save"></i> @lang('messages.save')</button>{!! Form::close() !!}
    </div></div>
    <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">@lang('hms::lang.configured_properties')</h3></div><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>@lang('hms::lang.property')</th><th>@lang('business.business_location')</th><th>@lang('business.time_zone')</th><th>@lang('hms::lang.business_date')</th><th>@lang('hms::lang.status')</th><th>@lang('messages.action')</th></tr></thead><tbody>
        @forelse($properties as $property)<tr><td><strong>{{ $property->name }}</strong><br><small>{{ $property->code }} @if($property->public_slug) /hotel/{{ $property->public_slug }} @endif</small></td><td>{{ optional($property->location)->name ?: '-' }}</td><td>{{ $property->timezone }}</td><td>{{ optional($property->business_date)->toDateString() }}</td><td><span class="label {{ $property->is_active ? 'bg-green' : 'bg-gray' }}">{{ $property->is_active ? __('hms::lang.active') : __('hms::lang.inactive') }}</span></td><td>
            <button class="btn btn-xs btn-primary" data-toggle="collapse" data-target="#property-{{ $property->id }}">@lang('messages.edit')</button>
        </td></tr><tr id="property-{{ $property->id }}" class="collapse"><td colspan="6">
            {!! Form::open(['route'=>['hms.properties.update',$property->id],'method'=>'put','class'=>'form-inline']) !!}
            {!! Form::text('name',$property->name,['class'=>'form-control','required','placeholder'=>__('hms::lang.property_name')]) !!}
            {!! Form::text('code',$property->code,['class'=>'form-control','required','placeholder'=>__('hms::lang.code')]) !!}
            {!! Form::select('location_id',$locations,$property->location_id,['class'=>'form-control select2','placeholder'=>__('lang_v1.none')]) !!}
            {!! Form::select('currency_id',$currencies,$property->currency_id,['class'=>'form-control select2']) !!}
            {!! Form::select('timezone',array_combine(timezone_identifiers_list(),timezone_identifiers_list()),$property->timezone,['class'=>'form-control select2','required']) !!}
            <input type="date" class="form-control" value="{{ optional($property->business_date)->toDateString() }}" readonly disabled title="@lang('hms::lang.business_date_night_audit_managed')">
            {!! Form::time('default_check_in_time',substr($property->default_check_in_time,0,5),['class'=>'form-control','required']) !!}
            {!! Form::time('default_check_out_time',substr($property->default_check_out_time,0,5),['class'=>'form-control','required']) !!}
            {!! Form::text('public_slug',$property->public_slug,['class'=>'form-control','placeholder'=>__('hms::lang.public_booking_slug')]) !!}
            <label>{!! Form::checkbox('booking_engine_enabled',1,$property->booking_engine_enabled) !!} @lang('hms::lang.booking_engine')</label>
            <label>{!! Form::checkbox('channel_manager_enabled',1,$property->channel_manager_enabled) !!} @lang('hms::lang.channel_manager')</label>
            <label>{!! Form::checkbox('is_active',1,$property->is_active) !!} @lang('hms::lang.active')</label>
            <button class="btn btn-primary">@lang('messages.update')</button>{!! Form::close() !!}
        </td></tr>@empty<tr><td colspan="6" class="text-center">@lang('hms::lang.no_properties')</td></tr>@endforelse
        </tbody></table>
    </div></div>
</section>
@endsection
