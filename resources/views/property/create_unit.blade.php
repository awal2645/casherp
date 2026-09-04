@extends('layouts.app')
@section('title','Add Unit')
@section('content')
<section class="content-header"><h1>Add Unit <small>{{ $property->name }}</small></h1></section>
<section class="content">
    <div class="box box-primary">
        {!! Form::open(['route'=>['property.units.store',$property]]) !!}
        <div class="box-body row">
            <div class="col-md-4"><div class="form-group">{!! Form::label('unit_code','Unit / space code:*') !!}{!! Form::text('unit_code',old('unit_code'),['class'=>'form-control','required','maxlength'=>100]) !!}</div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('unit_type','Unit type:') !!}{!! Form::text('unit_type',old('unit_type'),['class'=>'form-control','placeholder'=>'Apartment, office, shop, warehouse…','maxlength'=>100]) !!}</div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('listing_purpose','Business purpose:*') !!}{!! Form::select('listing_purpose',['rent'=>'Rent','lease'=>'Lease','sale'=>'Sale','not_listed'=>'Internal / not listed'],old('listing_purpose','rent'),['class'=>'form-control','required','id'=>'listing_purpose']) !!}</div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('monthly_rent','Monthly rent:') !!}{!! Form::text('monthly_rent',old('monthly_rent',0),['class'=>'form-control input_number']) !!}</div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('asking_price','Sale asking price:') !!}{!! Form::text('asking_price',old('asking_price'),['class'=>'form-control input_number']) !!}<span class="help-block">Required when the purpose is Sale.</span></div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('available_from','Available from:') !!}{!! Form::date('available_from',old('available_from'),['class'=>'form-control']) !!}</div></div>
            <div class="col-md-4"><div class="form-group">{!! Form::label('listing_status','Listing status:*') !!}{!! Form::select('listing_status',['draft'=>'Draft','active'=>'Active','paused'=>'Paused','under_offer'=>'Under offer','closed'=>'Closed'],old('listing_status','draft'),['class'=>'form-control','required']) !!}</div></div>
            <div class="col-md-4 tw-pt-8"><input type="hidden" name="is_listed" value="0"><label>{!! Form::checkbox('is_listed',1,old('is_listed',false)) !!} Publish in company listing workflows</label></div>
        </div>
        <div class="box-footer"><a class="btn btn-default" href="{{ route('property.index') }}">Cancel</a><button class="btn btn-primary pull-right"><i class="fa fa-save"></i> Save unit</button></div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
