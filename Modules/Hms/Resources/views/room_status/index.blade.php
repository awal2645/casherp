@extends('layouts.app')
@section('title', 'Room Status')
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>Room Status <small>live, derived front-desk and housekeeping state</small></h1></section>
<section class="content">
    <div class="callout callout-info"><p>The board derives occupancy from active bookings and readiness from housekeeping. It does not duplicate or manually overwrite either source of truth.</p></div>
    <div class="box box-primary"><div class="box-header with-border"><form method="get" class="form-inline">
        {!! Form::select('property_id',$properties,$propertyId,['class'=>'form-control select2','placeholder'=>'All accessible properties']) !!}
        <button class="btn btn-default"><i class="fa fa-filter"></i> Filter</button>
    </form></div><div class="box-body">
        <div class="row">
            @forelse($rooms as $room)
                @php($labels = ['ready'=>'success','occupied'=>'primary','reserved'=>'info','dirty'=>'warning','cleaning'=>'warning','inspected'=>'success','out_of_order'=>'danger'])
                <div class="col-lg-2 col-md-3 col-sm-4 col-xs-6">
                    <div class="small-box bg-{{ $labels[$room->operational_status] ?? 'gray' }}">
                        <div class="inner"><h3>{{ $room->room_number }}</h3><p>{{ optional($room->type)->type }} · {{ ucfirst(str_replace('_',' ',$room->operational_status)) }}</p>
                            @if($room->current_assignment)<small>{{ $room->current_assignment->guest_name ?: 'Guest' }} · until {{ \Carbon\Carbon::parse($room->current_assignment->hms_booking_departure_date_time)->format('M j, H:i') }}</small>@endif
                        </div><div class="icon"><i class="fa fa-bed"></i></div>
                    </div>
                </div>
            @empty<div class="col-md-12 text-center text-muted">No rooms are configured for the selected property.</div>@endforelse
        </div>
    </div></div>

    @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_stay_guests') || auth()->user()->can('hms.manage_room_moves'))
    <div class="box box-default"><div class="box-header with-border"><h3 class="box-title">Active stays, occupants and room moves</h3></div><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>Booking / guest</th><th>Rooms</th><th>Registered occupants</th><th>Controlled actions</th></tr></thead><tbody>
        @forelse($activeBookings as $booking)<tr>
            <td><strong>#{{ $booking->id }} · {{ optional($booking->contact)->name }}</strong><br><span class="label label-info">{{ ucfirst(str_replace('_',' ',$booking->hms_booking_status)) }}</span></td>
            <td>@foreach($booking->hms_booking_lines as $line)<div>{{ optional($line->room)->room_number }} ({{ $line->adults }} adult, {{ $line->childrens }} child)</div>@endforeach</td>
            <td>@forelse($booking->hms_booking_guests as $guest)<div>{{ $guest->full_name }} <small>{{ $guest->guest_type }}{{ $guest->is_primary ? ', primary' : '' }}</small>
                @can('hms.manage_stay_guests')<form method="post" action="{{ route('hms.booking_guests.destroy',$guest->id) }}" style="display:inline">@csrf @method('DELETE')<button class="btn btn-link btn-xs text-red" title="Remove occupant"><i class="fa fa-times"></i></button></form>@endcan
            </div>@empty<span class="text-muted">No named occupants</span>@endforelse</td>
            <td>
                @can('hms.manage_stay_guests')
                <form method="post" action="{{ route('hms.booking_guests.store',$booking->id) }}" class="form-inline tw-mb-2">@csrf
                    <input name="full_name" class="form-control input-sm" maxlength="255" required placeholder="Occupant full name">
                    {!! Form::select('guest_type',['adult'=>'Adult','child'=>'Child','infant'=>'Infant'],'adult',['class'=>'form-control input-sm']) !!}
                    {!! Form::select('hms_booking_line_id',$booking->hms_booking_lines->mapWithKeys(fn($line)=>[$line->id=>'Room '.optional($line->room)->room_number]),null,['class'=>'form-control input-sm','placeholder'=>'Assign room']) !!}
                    <label class="checkbox-inline"><input type="checkbox" name="is_primary" value="1"> Primary</label>
                    <button class="btn btn-primary btn-sm">Add occupant</button>
                </form>
                @endcan
                @can('hms.manage_room_moves')
                @foreach($booking->hms_booking_lines as $line)
                <form method="post" action="{{ route('hms.room_moves.store',$line->id) }}" class="form-inline tw-mt-2">@csrf
                    {!! Form::select('to_room_id',$availableRooms->where('hms_property_id',$booking->hms_property_id)->mapWithKeys(fn($room)=>[$room->id=>$room->room_number.' · '.optional($room->type)->type]),null,['class'=>'form-control input-sm','placeholder'=>'Move '.optional($line->room)->room_number.' to…','required']) !!}
                    <input name="reason" class="form-control input-sm" maxlength="2000" required placeholder="Reason for move">
                    <button class="btn btn-warning btn-sm">Move room</button>
                </form>
                @endforeach
                @endcan
            </td>
        </tr>@empty<tr><td colspan="4" class="text-center text-muted">No active stays for this property.</td></tr>@endforelse
        </tbody></table>
    </div></div>
    @endif
</section>
@endsection
