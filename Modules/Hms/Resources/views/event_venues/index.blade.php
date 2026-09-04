@extends('layouts.app')
@section('title', 'Event Venues')
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>Event Venues <small>Halls, gardens, meeting rooms and other bookable spaces</small></h1></section>
<section class="content">
    <div class="callout callout-info"><h4>Deposit control starts with a real venue</h4><p>Configure each rentable event space here. Wedding and venue-hire documents must select one of these spaces before a payment deposit or refundable security deposit can be recorded.</p></div>
    <div class="row">
        <div class="col-md-4"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Add event venue</h3></div>{!! Form::open(['route'=>'hms.event_venues.store']) !!}<div class="box-body">
            <div class="form-group"><label>Hotel / property:*</label>{!! Form::select('hms_property_id',$properties,null,['class'=>'form-control select2','required','placeholder'=>'Select property']) !!}</div>
            <div class="form-group"><label>Venue name:*</label><input name="name" class="form-control" maxlength="255" required placeholder="e.g. Garden Pavilion"></div>
            <div class="form-group"><label>Code:*</label><input name="code" class="form-control" maxlength="40" required placeholder="e.g. GARDEN-01"></div>
            <div class="form-group"><label>Venue type:*</label>{!! Form::select('venue_type',['event_hall'=>'Event hall','conference_room'=>'Conference room','garden'=>'Garden / outdoor venue','rooftop'=>'Rooftop','restaurant'=>'Restaurant / dining venue','meeting_room'=>'Meeting room','other'=>'Other'],null,['class'=>'form-control','required']) !!}</div>
            <div class="form-group"><label>Maximum capacity</label><input type="number" name="capacity" min="1" max="1000000" class="form-control"></div>
            <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="3" maxlength="3000"></textarea></div>
        </div><div class="box-footer"><button class="btn btn-primary"><i class="fa fa-plus"></i> Add venue</button></div>{!! Form::close() !!}</div></div>
        <div class="col-md-8"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Configured venues</h3></div><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Property</th><th>Venue</th><th>Type</th><th>Capacity</th><th>Status</th><th>Action</th></tr></thead><tbody>
            @forelse($venues as $venue)<tr><td>{{ optional($venue->property)->name }}</td><td><strong>{{ $venue->name }}</strong><br><small>{{ $venue->code }}</small></td><td>{{ ucfirst(str_replace('_',' ',$venue->venue_type)) }}</td><td>{{ $venue->capacity ?: '—' }}</td><td><span class="label {{ $venue->is_active ? 'label-success':'label-default' }}">{{ $venue->is_active ? 'Active':'Inactive' }}</span></td><td>{!! Form::open(['route'=>['hms.event_venues.toggle',$venue->id]]) !!}<button class="btn btn-xs btn-default">{{ $venue->is_active ? 'Deactivate':'Activate' }}</button>{!! Form::close() !!}</td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted">No event venues configured.</td></tr>@endforelse
        </tbody></table></div></div></div>
    </div>
</section>
@endsection
