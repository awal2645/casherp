@extends('layouts.app')
@section('title','Edit Unit')
@section('content')
<section class="content-header"><h1>Edit Unit <small>{{ $property->name }} / {{ $unit->unit_code }}</small></h1></section>
<section class="content"><div class="box box-primary">
{!! Form::open(['route'=>['property.units.update',$unit],'method'=>'put']) !!}
<div class="box-body row">
    <div class="col-md-4"><div class="form-group"><label>Unit / space code:*</label><input class="form-control" name="unit_code" required maxlength="100" value="{{ old('unit_code',$unit->unit_code) }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Unit type:</label><input class="form-control" name="unit_type" maxlength="100" value="{{ old('unit_type',$unit->unit_type) }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Business purpose:*</label>{!! Form::select('listing_purpose',['rent'=>'Rent','lease'=>'Lease','sale'=>'Sale','not_listed'=>'Internal / not listed'],old('listing_purpose',$unit->listing_purpose),['class'=>'form-control','required']) !!}</div></div>
    <div class="col-md-4"><div class="form-group"><label>Monthly rent:</label><input class="form-control input_number" name="monthly_rent" value="{{ old('monthly_rent',$unit->monthly_rent) }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Sale asking price:</label><input class="form-control input_number" name="asking_price" value="{{ old('asking_price',$unit->asking_price) }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Available from:</label><input type="date" class="form-control" name="available_from" value="{{ old('available_from',optional($unit->available_from)->toDateString()) }}"></div></div>
    <div class="col-md-4"><div class="form-group"><label>Occupancy / operational status:*</label>{!! Form::select('status',['vacant'=>'Vacant','occupied'=>'Occupied','reserved'=>'Reserved','maintenance'=>'Maintenance','unavailable'=>'Unavailable'],old('status',$unit->status),['class'=>'form-control','required']) !!}</div></div>
    <div class="col-md-4"><div class="form-group"><label>Listing status:*</label>{!! Form::select('listing_status',['draft'=>'Draft','active'=>'Active','paused'=>'Paused','under_offer'=>'Under offer','closed'=>'Closed'],old('listing_status',$unit->listing_status),['class'=>'form-control','required']) !!}</div></div>
    <div class="col-md-4 tw-pt-8"><input type="hidden" name="is_listed" value="0"><label>{!! Form::checkbox('is_listed',1,old('is_listed',$unit->is_listed)) !!} Publish in company listing workflows</label></div>
</div>
<div class="box-footer"><a class="btn btn-default" href="{{ route('property.units.index') }}">Cancel</a><button class="btn btn-primary pull-right"><i class="fa fa-save"></i> Save changes</button></div>
{!! Form::close() !!}</div></section>
@endsection
