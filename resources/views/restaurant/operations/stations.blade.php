@extends('layouts.app')
@section('title', 'Kitchen Stations')
@section('content')
<section class="content-header"><h1>Kitchen stations <small>route each menu item to the correct preparation area</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row">
        <div class="col-md-4">
            <div class="box box-primary"><div class="box-header"><h3 class="box-title">Add station</h3></div>
                <form method="post" action="{{ route('restaurant-operations.stations.store') }}">@csrf
                    <div class="box-body">
                        <div class="form-group"><label>Location *</label>{!! Form::select('location_id', $locations, old('location_id'), ['class'=>'form-control select2','required','placeholder'=>'Select location']) !!}</div>
                        <div class="form-group"><label>Name *</label><input class="form-control" name="name" value="{{ old('name') }}" maxlength="120" required></div>
                        <div class="form-group"><label>Code</label><input class="form-control" name="code" value="{{ old('code') }}" maxlength="40"><p class="help-block">Leave blank to generate from the name.</p></div>
                        <div class="form-group"><label>Station type *</label>{!! Form::select('station_type', config('restaurant_operations.station_types'), old('station_type','main'), ['class'=>'form-control','required']) !!}</div>
                        <div class="form-group"><label>Service target (minutes) *</label><input class="form-control" type="number" name="service_level_minutes" min="1" max="1440" value="{{ old('service_level_minutes',15) }}" required></div>
                        <label class="checkbox-inline"><input type="checkbox" name="is_default" value="1"> Default fallback</label>
                        <input type="hidden" name="is_active" value="1">
                    </div><div class="box-footer"><button class="btn btn-primary">Save station</button></div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="box"><div class="box-header"><h3 class="box-title">Active station routing</h3></div><div class="box-body table-responsive">
                <table class="table table-bordered table-striped"><thead><tr><th>Location</th><th>Station</th><th>Type</th><th>Target</th><th>Mapped items</th><th>Action</th></tr></thead><tbody>
                @forelse($stations as $station)
                    <tr><td>{{ $locations[$station->location_id] ?? $station->location_id }}</td><td>{{ $station->name }} @if($station->is_default)<span class="label label-primary">Default</span>@endif</td><td>{{ config('restaurant_operations.station_types.'.$station->station_type, $station->station_type) }}</td><td>{{ $station->service_level_minutes }} min</td><td>{{ $station->product_mappings_count }}</td><td>
                        <form method="post" action="{{ route('restaurant-operations.stations.destroy',$station->id) }}" class="inline-form">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Disable this station?')">Disable</button></form>
                    </td></tr>
                    <tr><td colspan="6"><form class="form-inline" method="post" action="{{ route('restaurant-operations.stations.products.store',$station->id) }}">@csrf
                        {!! Form::select('product_id',$products,null,['class'=>'form-control select2','placeholder'=>'Menu product','required']) !!}
                        {!! Form::select('variation_id',$variations,null,['class'=>'form-control select2','placeholder'=>'All variations']) !!}
                        <input class="form-control" type="number" name="preparation_minutes" min="1" placeholder="Prep min">
                        <button class="btn btn-default">Map to {{ $station->name }}</button>
                    </form></td></tr>
                @empty<tr><td colspan="6" class="text-center">No kitchen station has been configured.</td></tr>@endforelse
                </tbody></table>
            </div></div>
        </div>
    </div>
</section>
@endsection
