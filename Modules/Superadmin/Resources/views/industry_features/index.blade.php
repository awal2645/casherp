@extends('layouts.app')
@section('title', 'Industry feature profiles')
@section('content')
    @include('superadmin::layouts.nav')
    <section class="content-header"><h1>Industry feature profiles <small>Set the default modules for new companies</small></h1></section>
    <section class="content">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i>
            <strong>HRM and Hotel Management are independent modules.</strong>
            Human Resource Management (HRM) controls staff operations. Hotel Management System (HMS) controls rooms, guests, and bookings. Selecting both gives a company two separate workspaces; disabling either one does not disable the other.
            HRM starts enabled in all six launch-industry profiles, while Super Admin remains able to adjust each industry's defaults.
        </div>
        @foreach($industries as $industry)
            <div class="box box-primary">
                {!! Form::open(['route' => ['superadmin.industry-features.update', $industry], 'method' => 'put']) !!}
                <div class="box-header with-border"><h3 class="box-title">{{ $industry->name }}</h3></div>
                <div class="box-body row">
                    @foreach($features as $feature)
                        <div class="col-md-4 col-sm-6">
                            <div class="checkbox">
                                <label>
                                    {!! Form::checkbox('feature_ids[]', $feature->id, $industry->features->contains('id', $feature->id), ['class' => 'input-icheck']) !!}
                                    {{ $feature->name }}
                                    @if($feature->code === 'hrm')
                                        <small class="text-muted">&mdash; employees, attendance, leave, payroll</small>
                                    @elseif($feature->code === 'hms')
                                        <small class="text-muted">&mdash; rooms, guests, bookings, housekeeping</small>
                                    @endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                    <div class="clearfix"></div>
                    <div class="col-md-12"><div class="checkbox"><label>{!! Form::checkbox('apply_to_existing', 1, false, ['class' => 'input-icheck']) !!} Apply this profile to all existing companies in this industry (this replaces their current feature choices).</label></div></div>
                </div>
                <div class="box-footer"><button class="btn btn-primary pull-right">@lang('messages.update')</button></div>
                {!! Form::close() !!}
            </div>
        @endforeach
    </section>
@endsection
