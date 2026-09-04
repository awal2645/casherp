@extends('layouts.app')
@section('title', 'Restaurant Order Fulfilment')
@section('content')
<section class="content-header"><h1>Order fulfilment <small>dine-in, takeaway, delivery, room service and events</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="box"><div class="box-body table-responsive">
        <form method="get" class="form-inline tw-mb-4">
            {!! Form::select('service_channel',$availableChannels,request('service_channel'),['class'=>'form-control','placeholder'=>'All channels']) !!}
            {!! Form::select('status',config('restaurant_operations.fulfilment_statuses'),request('status'),['class'=>'form-control','placeholder'=>'All statuses']) !!}
            <button class="btn btn-default">Filter</button>
        </form>
        <table class="table table-bordered table-striped"><thead><tr><th>Order</th><th>Channel</th><th>Status</th><th>Room/event context</th><th>Promised</th><th>Update</th></tr></thead><tbody>
        @forelse($orders as $order)<tr>
            <td>#{{ $order->transaction_id }}</td>
            <td>{{ config('restaurant_operations.service_channels.'.$order->service_channel,$order->service_channel) }}</td>
            <td><span class="label label-info">{{ config('restaurant_operations.fulfilment_statuses.'.$order->status,$order->status) }}</span></td>
            <td>{{ $order->room_reference ?: trim(($order->source_type ?: '').' '.($order->source_id ?: '')) ?: '—' }}</td>
            <td>{{ optional($order->promised_at)->format('Y-m-d H:i') ?: '—' }}</td>
            <td><form method="post" action="{{ route('restaurant-operations.fulfilments.update',$order->id) }}" class="form-inline">@csrf @method('PATCH')
                {!! Form::select('status',collect(config('restaurant_operations.fulfilment_transitions.'.$order->status,[]))->mapWithKeys(fn($value)=>[$value=>config('restaurant_operations.fulfilment_statuses.'.$value,$value)]),null,['class'=>'form-control input-sm','placeholder'=>'Next status','required']) !!}
                {!! Form::select('service_channel',$availableChannels,$order->service_channel,['class'=>'form-control input-sm','required']) !!}
                {!! Form::select('hms_booking_id',$hotelSources['hms_booking'],$order->source_type==='hms_booking' ? $order->source_id : null,['class'=>'form-control input-sm','placeholder'=>'Hotel stay (room service)']) !!}
                {!! Form::select('hms_event_id',$hotelSources['hms_event'],$order->source_type==='hms_event' ? $order->source_id : null,['class'=>'form-control input-sm','placeholder'=>'Event (banqueting)']) !!}
                <input class="form-control input-sm" name="room_reference" value="{{ $order->room_reference }}" placeholder="Room reference">
                <button class="btn btn-primary btn-sm">Update</button>
            </form></td>
        </tr>@empty<tr><td colspan="6" class="text-center">No restaurant orders have entered the enhanced fulfilment pipeline.</td></tr>@endforelse
        </tbody></table>
        {{ $orders->links() }}
    </div></div>
</section>
@endsection
